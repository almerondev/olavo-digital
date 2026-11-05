<?php

namespace Tests\Feature\LiveTv;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Home\Models\HomeLayout;
use App\Modules\Home\Services\HomeService;
use App\Modules\LiveTv\Events\LiveStreamStarted;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Services\MaterialWorkflow;
use App\Support\YouTubeUrl;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LiveTvTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://www.youtube.com/live/abc123DEF45';

    private User $admin;
    private User $student;
    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, CatalogSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', Role::ADMIN)->first());
        $this->student = User::factory()->create();
        $this->student->roles()->attach(Role::where('slug', Role::STUDENT)->first());
        Student::create(['user_id' => $this->student->id]);
        $this->teacher = Teacher::create(['slug' => 'prof', 'display_name' => 'Prof']);
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Aulão',
            'youtube_url' => self::URL,
            'teacher_id' => $this->teacher->id,
            'starts_at' => now()->toIso8601String(),
            'ends_at' => now()->addHours(2)->toIso8601String(),
        ];
    }

    private function goLive(array $overrides = []): int
    {
        return $this->as($this->admin)->postJson('/api/v1/admin/live/go-live', $this->payload($overrides))->assertCreated()->json('data.stream_id');
    }

    private function schedule(array $overrides = []): LiveSchedule
    {
        return LiveSchedule::create($overrides + [
            'title' => 'Plantão', 'teacher_id' => $this->teacher->id,
            'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(),
        ]);
    }

    public function test_understands_youtube_link_formats(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=abc123DEF45',
            'https://youtube.com/watch?feature=share&v=abc123DEF45',
            'https://youtu.be/abc123DEF45?si=x',
            'https://www.youtube.com/live/abc123DEF45?feature=share',
            'https://m.youtube.com/watch?v=abc123DEF45',
            'https://www.youtube.com/embed/abc123DEF45',
        ] as $url) {
            $this->assertSame('abc123DEF45', YouTubeUrl::id($url), $url);
        }

        foreach (['https://vimeo.com/123', 'https://youtube.com.golpe.test/watch?v=abc123DEF45', 'qualquer coisa', 'https://www.youtube.com/@canal'] as $url) {
            $this->assertNull(YouTubeUrl::id($url), $url);
        }
    }

    public function test_admin_pastes_link_and_goes_live(): void
    {
        Event::fake([LiveStreamStarted::class]);

        $id = $this->goLive();

        $this->as($this->student)->getJson('/api/v1/tv/now')->assertOk()
            ->assertJsonPath('data.live.stream_id', $id)
            ->assertJsonPath('data.live.schedule.title', 'Aulão')
            ->assertJsonPath('data.live.embed_url', 'https://www.youtube-nocookie.com/embed/abc123DEF45?autoplay=1&mute=1&rel=0');

        Event::assertDispatchedTimes(LiveStreamStarted::class, 1);
    }

    public function test_student_does_not_receive_original_link_in_schedule(): void
    {
        $this->schedule(['youtube_url' => self::URL]);

        $this->as($this->student)->getJson('/api/v1/tv/schedule')->assertJsonMissingPath('data.0.youtube_url');
    }

    public function test_invalid_link_is_refused(): void
    {
        $this->as($this->admin)->postJson('/api/v1/admin/live/go-live', $this->payload(['youtube_url' => 'https://exemplo.test/video']))
            ->assertStatus(422)->assertJsonValidationErrors('youtube_url');

        $this->assertSame(0, LiveStream::count());
    }

    public function test_only_one_broadcast_at_a_time(): void
    {
        $this->goLive();

        $this->postJson('/api/v1/admin/live/go-live', $this->payload(['title' => 'Outra']))
            ->assertStatus(409)->assertJsonPath('code', 'stream_on_air');
    }

    public function test_scheduled_broadcast_starts_only_with_link(): void
    {
        $empty = $this->schedule();
        $ready = $this->schedule(['youtube_url' => self::URL]);

        $this->as($this->admin)->postJson("/api/v1/admin/live/schedules/{$empty->id}/start")->assertStatus(422)->assertJsonPath('code', 'youtube_url_required');
        $this->postJson("/api/v1/admin/live/schedules/{$ready->id}/start")->assertOk();

        $this->assertSame('live', $ready->fresh()->status->value);
    }

    public function test_ending_creates_recording_draft_with_same_link(): void
    {
        $history = Category::where('slug', 'historia')->first();
        $id = $this->goLive(['category_id' => $history->id, 'description' => 'Revisão']);

        $this->postJson("/api/v1/admin/live/streams/{$id}/end")->assertNoContent();
        $this->postJson("/api/v1/admin/live/streams/{$id}/end")->assertStatus(409);

        $stream = LiveStream::find($id);
        $material = $stream->material;

        $this->assertSame(MaterialStatus::Draft, $material->status);
        $this->assertSame(MaterialType::ExternalVideo, $material->type);
        $this->assertSame(self::URL, $material->external_url);
        $this->assertSame('ended', $stream->schedule->status->value);

        app(MaterialWorkflow::class)->publish($material, $this->admin);

        $this->as($this->student)->getJson('/api/v1/tv/now')
            ->assertJsonPath('data.live', null)
            ->assertJsonPath('data.recordings.0.title', 'Aulão');
        $this->getJson('/api/v1/teachers/prof')->assertJsonPath('data.live.0.title', 'Aulão');
        $this->getJson("/api/v1/materials/{$material->slug}")->assertJsonPath('data.embed_url', 'https://www.youtube-nocookie.com/embed/abc123DEF45?autoplay=1&rel=0');
    }

    public function test_programming_can_be_turned_off(): void
    {
        $this->schedule(['title' => 'Mais tarde']);

        $this->as($this->student)->getJson('/api/v1/tv/now')
            ->assertJsonPath('data.schedule_enabled', true)
            ->assertJsonPath('data.upcoming.0.title', 'Mais tarde');

        $this->as($this->admin)->putJson('/api/v1/admin/live/settings', ['schedule_enabled' => false])->assertOk();

        $this->as($this->student)->getJson('/api/v1/tv/now')
            ->assertJsonPath('data.schedule_enabled', false)
            ->assertJsonCount(0, 'data.upcoming');
        $this->getJson('/api/v1/tv/schedule')->assertJsonCount(0, 'data');
    }

    public function test_live_programming_off_still_shows_broadcast(): void
    {
        $this->goLive();
        $this->putJson('/api/v1/admin/live/settings', ['schedule_enabled' => false]);

        $this->as($this->student)->getJson('/api/v1/tv/now')->assertJsonPath('data.live.schedule.title', 'Aulão');
    }

    public function test_broadcast_of_a_course_reaches_only_its_students(): void
    {
        $esa = Course::create(['name' => 'ESA', 'slug' => 'esa']);
        $id = $this->goLive(['course_id' => $esa->id]);

        $this->as($this->student)->getJson('/api/v1/tv/now')->assertJsonPath('data.live', null);

        $this->student->student->courses()->attach($esa);

        $this->as($this->student->fresh())->getJson('/api/v1/tv/now')->assertJsonPath('data.live.stream_id', $id);
    }

    public function test_restricted_group_broadcast_hides_from_outsiders(): void
    {
        $group = StudentGroup::create(['name' => 'Turma', 'slug' => 'turma']);
        $this->goLive(['group_ids' => [$group->id]]);

        $this->as($this->student)->getJson('/api/v1/tv/now')->assertJsonPath('data.live', null);

        $group->students()->attach($this->student->student);
        $this->as($this->student->fresh())->getJson('/api/v1/tv/now')->assertJsonPath('data.live.schedule.title', 'Aulão');
    }

    public function test_live_broadcast_takes_over_the_home_hero(): void
    {
        HomeLayout::create(['name' => 'Início', 'surface' => 'home', 'status' => 'published', 'published_at' => now()]);
        HomeLayout::create(['name' => 'Materiais', 'surface' => 'materials', 'status' => 'published', 'published_at' => now()]);
        app(HomeService::class)->flush();
        $this->goLive(['title' => 'No ar']);

        $this->as($this->student)->getJson('/api/v1/home')
            ->assertJsonPath('data.hero.0.title', 'No ar')
            ->assertJsonPath('data.hero.0.is_live', true);

        $this->getJson('/api/v1/home?surface=materials')->assertJsonCount(0, 'data.hero');
    }

    public function test_admin_manages_schedule_rules(): void
    {
        $this->as($this->admin)->postJson('/api/v1/admin/live/schedules', $this->payload(['ends_at' => now()->subDay()->toIso8601String()]))
            ->assertStatus(422)->assertJsonValidationErrors('ends_at');

        $id = $this->postJson('/api/v1/admin/live/schedules', $this->payload())->assertCreated()->json('data.id');
        $this->putJson("/api/v1/admin/live/schedules/{$id}", $this->payload(['title' => 'Novo']))->assertOk()->assertJsonPath('data.title', 'Novo');
        $this->postJson("/api/v1/admin/live/schedules/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/admin/live/schedules/{$id}/cancel")->assertStatus(409);
    }

    public function test_only_staff_manages_live(): void
    {
        $this->as($this->student)->postJson('/api/v1/admin/live/go-live', $this->payload())->assertForbidden();
    }
}
