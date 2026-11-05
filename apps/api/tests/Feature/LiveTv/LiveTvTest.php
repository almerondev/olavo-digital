<?php

namespace Tests\Feature\LiveTv;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Home\Models\HomeLayout;
use App\Modules\Home\Services\HomeService;
use App\Modules\LiveTv\Events\LiveStreamStarted;
use App\Modules\LiveTv\Jobs\EndDisconnectedStream;
use App\Modules\LiveTv\Models\LiveChannel;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\LiveTv\Providers\LiveProvider;
use App\Modules\LiveTv\Services\LiveStreamService;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialWorkflow;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LiveTvTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private Teacher $teacher;
    private LiveChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, CatalogSeeder::class]);
        Storage::fake('uploads_private');
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', Role::ADMIN)->first());
        $this->student = User::factory()->create();
        $this->student->roles()->attach(Role::where('slug', Role::STUDENT)->first());
        Student::create(['user_id' => $this->student->id]);
        $this->teacher = Teacher::create(['slug' => 'prof', 'display_name' => 'Prof']);

        $provider = app(LiveProvider::class);
        $this->channel = LiveChannel::create(['name' => 'Estúdio', 'provider' => $provider->name()] + $provider->createInput('Estúdio'));
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    private function schedule(array $overrides = []): LiveSchedule
    {
        return LiveSchedule::create($overrides + [
            'live_channel_id' => $this->channel->id,
            'title' => 'Aulão',
            'teacher_id' => $this->teacher->id,
            'starts_at' => now()->addMinutes(5),
            'ends_at' => now()->addHours(2),
        ]);
    }

    private function webhook(string $type, string $session = 'sessao-1')
    {
        $body = json_encode(['type' => $type, 'input_id' => $this->channel->provider_input_id, 'session' => $session]);

        return $this->call('POST', '/api/v1/webhooks/live/simulated', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_LIVE_SIGNATURE' => app(LiveProvider::class)->sign($body),
        ], $body);
    }

    public function test_stream_key_is_encrypted_at_rest_and_never_listed(): void
    {
        $this->assertNotSame($this->channel->stream_key, DB::table('live_channels')->value('stream_key'));

        $this->as($this->admin)->getJson('/api/v1/admin/live/channels')->assertOk()
            ->assertJsonPath('meta.simulated', true)
            ->assertJsonMissingPath('data.0.stream_key');
    }

    public function test_viewing_key_needs_permission_and_is_audited(): void
    {
        $this->as($this->admin)->getJson("/api/v1/admin/live/channels/{$this->channel->id}/key")
            ->assertOk()->assertJsonPath('data.stream_key', $this->channel->stream_key);

        $this->assertDatabaseHas('activity_logs', ['action' => 'live.key_viewed']);

        $role = Role::create(['slug' => 'operador', 'name' => 'Operador']);
        $role->permissions()->sync(\App\Modules\Access\Models\Permission::whereIn('slug', ['panel.admin', 'live.manage'])->pluck('id'));
        $operator = User::factory()->create();
        $operator->roles()->attach($role);

        $this->as($operator)->getJson('/api/v1/admin/live/channels')->assertOk();
        $this->getJson("/api/v1/admin/live/channels/{$this->channel->id}/key")->assertForbidden();
    }

    public function test_key_can_be_rotated_only_off_air(): void
    {
        $old = $this->channel->stream_key;

        $this->as($this->admin)->postJson("/api/v1/admin/live/channels/{$this->channel->id}/key")->assertOk();
        $this->assertNotSame($old, $this->channel->fresh()->stream_key);

        $this->webhook('connected');
        $this->postJson("/api/v1/admin/live/channels/{$this->channel->id}/key")->assertStatus(409)->assertJsonPath('code', 'channel_on_air');
    }

    public function test_webhook_rejects_wrong_signature_or_provider(): void
    {
        $body = json_encode(['type' => 'connected', 'input_id' => $this->channel->provider_input_id, 'session' => 'x']);

        $this->call('POST', '/api/v1/webhooks/live/simulated', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_LIVE_SIGNATURE' => 'falsa'], $body)
            ->assertUnauthorized();
        $this->call('POST', '/api/v1/webhooks/live/outro', [], [], [], ['HTTP_ACCEPT' => 'application/json'], $body)->assertNotFound();

        $this->assertSame(0, LiveStream::count());
    }

    public function test_connection_starts_the_planned_schedule_and_notifies_once(): void
    {
        Event::fake([LiveStreamStarted::class]);
        $schedule = $this->schedule();

        $this->webhook('connected')->assertNoContent();
        $this->webhook('connected')->assertNoContent();

        $this->assertSame(1, LiveStream::count());
        $this->assertSame('live', $schedule->fresh()->status->value);
        Event::assertDispatchedTimes(LiveStreamStarted::class, 1);
    }

    public function test_connection_picks_the_schedule_closest_to_now(): void
    {
        $far = $this->schedule(['title' => 'Distante', 'starts_at' => now()->addMinutes(25)]);
        $near = $this->schedule(['title' => 'Próxima', 'starts_at' => now()->addMinutes(2)]);

        $this->webhook('connected');

        $this->assertSame('live', $near->fresh()->status->value);
        $this->assertSame('scheduled', $far->fresh()->status->value);
    }

    public function test_connection_without_schedule_creates_adhoc_entry(): void
    {
        $this->webhook('connected');

        $this->assertTrue(LiveSchedule::first()->is_adhoc);
    }

    public function test_brief_signal_loss_does_not_end_the_class(): void
    {
        Queue::fake();
        Event::fake([LiveStreamStarted::class]);
        $schedule = $this->schedule();

        $this->webhook('connected');
        $this->webhook('disconnected');

        $stream = LiveStream::first();
        $this->assertNotNull($stream->disconnected_at);
        Queue::assertPushed(EndDisconnectedStream::class);

        $this->webhook('connected', 'sessao-2');

        $this->assertNull($stream->fresh()->disconnected_at);
        $this->assertSame(1, LiveStream::count());
        Event::assertDispatchedTimes(LiveStreamStarted::class, 1);

        // O encerramento agendado na queda não vale mais.
        app(LiveStreamService::class)->endIfStillDisconnected($stream->id, $stream->disconnected_at?->toIso8601String() ?? now()->subMinute()->toIso8601String());
        $this->assertNull($stream->fresh()->ended_at);
        $this->assertSame('live', $schedule->fresh()->status->value);
    }

    public function test_long_signal_loss_ends_at_the_moment_it_dropped(): void
    {
        Queue::fake();
        $schedule = $this->schedule();
        $this->webhook('connected');
        $this->webhook('disconnected');

        $stream = LiveStream::first();
        $droppedAt = $stream->disconnected_at;

        $this->travel(4)->minutes();
        app(LiveStreamService::class)->endIfStillDisconnected($stream->id, $droppedAt->toIso8601String());

        $this->assertEquals($droppedAt, $stream->fresh()->ended_at);
        $this->assertSame('ended', $schedule->fresh()->status->value);
    }

    public function test_recording_becomes_a_draft_once(): void
    {
        $history = Category::where('slug', 'historia')->first();
        $this->schedule(['category_id' => $history->id, 'description' => 'Revisão']);
        Queue::fake();
        $this->webhook('connected');
        $this->as($this->admin)->postJson('/api/v1/admin/live/streams/'.LiveStream::first()->id.'/end')->assertNoContent();

        $this->webhook('recording_ready');
        $this->webhook('recording_ready');

        $material = LiveStream::first()->material;
        $this->assertSame(1, Material::count());
        $this->assertSame(MaterialStatus::Draft, $material->status);
        $this->assertSame('Aulão', $material->title);
        $this->assertSame($this->teacher->id, $material->teacher_id);
        $this->assertSame($history->id, $material->categories()->first()->id);

        app(MaterialWorkflow::class)->publish($material, $this->admin);

        $this->as($this->student)->getJson('/api/v1/tv/now')->assertJsonPath('data.recordings.0.title', 'Aulão');
        $this->getJson('/api/v1/teachers/prof')->assertJsonPath('data.live.0.title', 'Aulão');
    }

    public function test_page_shows_live_or_next_broadcasts(): void
    {
        $this->schedule(['title' => 'Mais tarde', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);

        $this->as($this->student)->getJson('/api/v1/tv/now')->assertOk()
            ->assertJsonPath('data.live', null)
            ->assertJsonPath('data.upcoming.0.title', 'Mais tarde');

        $this->schedule(['title' => 'Agora']);
        $this->webhook('connected');

        $stream = LiveStream::first();
        $this->getJson('/api/v1/tv/now')
            ->assertJsonPath('data.live.schedule.title', 'Agora')
            ->assertJsonPath('data.live.stream_id', $stream->id)
            ->assertJsonPath('data.live.schedule.teacher.name', 'Prof');

        $this->getJson("/api/v1/tv/streams/{$stream->id}/playback")->assertOk()->assertJsonPath('data.kind', 'file');
    }

    public function test_restricted_broadcast_hides_from_outsiders(): void
    {
        $group = StudentGroup::create(['name' => 'Turma', 'slug' => 'turma']);
        $schedule = $this->schedule();
        $schedule->syncAudience([$group->id]);
        $this->webhook('connected');
        $stream = LiveStream::first();

        $this->as($this->student)->getJson('/api/v1/tv/now')->assertJsonPath('data.live', null);
        $this->getJson("/api/v1/tv/streams/{$stream->id}/playback")->assertNotFound();

        $group->students()->attach($this->student->student);
        $this->as($this->student->fresh())->getJson('/api/v1/tv/now')->assertJsonPath('data.live.stream_id', $stream->id);
    }

    public function test_ended_stream_has_no_playback(): void
    {
        Queue::fake();
        $this->webhook('connected');
        $stream = LiveStream::first();
        app(LiveStreamService::class)->endNow($stream);

        $this->as($this->student)->getJson("/api/v1/tv/streams/{$stream->id}/playback")->assertNotFound();
    }

    public function test_live_broadcast_takes_over_the_home_hero(): void
    {
        HomeLayout::create(['name' => 'Início', 'surface' => 'home', 'status' => 'published', 'published_at' => now()]);
        HomeLayout::create(['name' => 'Materiais', 'surface' => 'materials', 'status' => 'published', 'published_at' => now()]);
        app(HomeService::class)->flush();
        $this->schedule(['title' => 'No ar']);
        $this->webhook('connected');

        $this->as($this->student)->getJson('/api/v1/home')
            ->assertJsonPath('data.hero.0.title', 'No ar')
            ->assertJsonPath('data.hero.0.is_live', true);

        $this->getJson('/api/v1/home?surface=materials')->assertJsonCount(0, 'data.hero');
    }

    public function test_admin_manages_schedule_rules(): void
    {
        $payload = ['live_channel_id' => $this->channel->id, 'title' => 'Plantão', 'starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => now()->addDay()->addHour()->toIso8601String()];

        $this->as($this->admin)->postJson('/api/v1/admin/live/schedules', ['ends_at' => $payload['starts_at']] + $payload)->assertStatus(422)->assertJsonValidationErrors('ends_at');

        $id = $this->postJson('/api/v1/admin/live/schedules', $payload)->assertCreated()->json('data.id');
        $this->putJson("/api/v1/admin/live/schedules/{$id}", ['title' => 'Plantão novo'] + $payload)->assertOk()->assertJsonPath('data.title', 'Plantão novo');
        $this->postJson("/api/v1/admin/live/schedules/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/admin/live/schedules/{$id}/cancel")->assertStatus(409);
        $this->putJson("/api/v1/admin/live/schedules/{$id}", $payload)->assertStatus(409);
    }

    public function test_only_staff_manages_live(): void
    {
        $this->as($this->student)->getJson('/api/v1/admin/live/channels')->assertForbidden();
    }
}
