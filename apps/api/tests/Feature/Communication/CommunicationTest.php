<?php

namespace Tests\Feature\Communication;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Communication\Models\NotificationRecipient;
use App\Modules\Communication\Models\PlatformNotification;
use App\Modules\Communication\Services\AnnouncementBoard;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Services\LiveStreamService;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Events\MaterialPublished;
use App\Modules\Materials\Models\Material;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CourseSeeder;
use Database\Seeders\HomeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $enem;
    private User $esa;
    private Course $enemCourse;
    private Course $esaCourse;
    private Category $enemMath;
    private Category $esaMath;
    private Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, CatalogSeeder::class, CourseSeeder::class, HomeSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', Role::ADMIN)->value('id'));

        $this->enemCourse = Course::where('slug', 'preparatorio-enem')->firstOrFail();
        $this->esaCourse = Course::where('slug', 'preparatorio-esa')->firstOrFail();
        $this->enem = $this->student($this->enemCourse);
        $this->esa = $this->student($this->esaCourse);

        $this->enemMath = Category::where('course_id', $this->enemCourse->id)->where('name', 'Matemática')->firstOrFail();
        $this->esaMath = Category::where('course_id', $this->esaCourse->id)->where('name', 'Matemática')->firstOrFail();

        $this->teacher = Teacher::create(['user_id' => User::factory()->create()->id, 'display_name' => 'Helena Prado', 'slug' => 'helena-prado', 'show_in_cast' => true]);
    }

    private function student(Course $course): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::STUDENT)->value('id'));
        Student::create(['user_id' => $user->id])->courses()->attach($course);

        return $user;
    }

    private function material(string $title, Category $category, array $extra = []): Material
    {
        $material = Material::create([
            'slug' => Str::slug($title),
            'title' => $title,
            'description' => $extra['description'] ?? null,
            'type' => $extra['type'] ?? MaterialType::Pdf,
            'status' => MaterialStatus::Published,
            'visibility' => 'all',
            'teacher_id' => $this->teacher->id,
            'published_at' => now()->subMinute(),
        ]);
        $material->categories()->attach($category, ['is_primary' => true]);

        return $material;
    }

    private function announcement(array $attributes = []): Announcement
    {
        return Announcement::create([
            'title' => 'Aviso',
            'body' => 'Corpo do aviso',
            'display_mode' => 'card',
            'published_at' => now()->subMinute(),
            ...$attributes,
        ]);
    }

    private function homeAnnouncements(User $user): array
    {
        return array_column($this->actingAs($user)->getJson('/api/v1/home')->assertOk()->json('data.announcements'), 'title');
    }

    public function test_announcement_respects_course_group_and_period(): void
    {
        $group = StudentGroup::create(['name' => 'Turma A', 'slug' => 'turma-a', 'kind' => 'class']);
        $group->students()->attach($this->enem->student);

        $this->announcement(['title' => 'Geral']);
        $this->announcement(['title' => 'Só ESA', 'course_id' => $this->esaCourse->id]);
        $this->announcement(['title' => 'Só turma'])->syncAudience([$group->id]);
        $this->announcement(['title' => 'Futuro', 'published_at' => now()->addDay()]);
        $this->announcement(['title' => 'Expirado', 'published_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);

        $this->assertEqualsCanonicalizing(['Geral', 'Só turma'], $this->homeAnnouncements($this->enem));
        $this->assertEqualsCanonicalizing(['Geral', 'Só ESA'], $this->homeAnnouncements($this->esa));
    }

    public function test_read_removes_common_announcement_but_keeps_pinned(): void
    {
        $common = $this->announcement(['title' => 'Comum']);
        $pinned = $this->announcement(['title' => 'Fixado', 'is_pinned' => true, 'requires_ack' => true]);

        $this->actingAs($this->enem)->postJson("/api/v1/announcements/{$common->id}/read")->assertNoContent();
        $this->postJson("/api/v1/announcements/{$pinned->id}/read")->assertNoContent();
        $this->postJson("/api/v1/announcements/{$pinned->id}/read")->assertNoContent();

        $this->assertSame(['Fixado'], $this->homeAnnouncements($this->enem));
        $this->assertSame(1, $pinned->reads()->count());
        $this->assertNotNull($this->getJson('/api/v1/announcements')->json('data.0.read_at'));
        $this->actingAs($this->admin)->getJson("/api/v1/admin/announcements/{$pinned->id}/reads")->assertOk()->assertJsonPath('data.0.name', $this->enem->name);
    }

    public function test_student_cannot_confirm_announcement_of_other_course(): void
    {
        $other = $this->announcement(['course_id' => $this->esaCourse->id]);

        $this->actingAs($this->enem)->postJson("/api/v1/announcements/{$other->id}/read")->assertNotFound();
    }

    public function test_admin_manages_announcement_and_notification_reaches_only_its_audience(): void
    {
        $payload = [
            'title' => 'Prova ESA', 'body' => 'Datas novas', 'display_mode' => 'banner', 'priority' => 2,
            'course_id' => $this->esaCourse->id, 'published_at' => now()->subMinute()->toIso8601String(), 'expires_at' => null,
            'is_pinned' => true, 'requires_ack' => false, 'send_notification' => true, 'group_ids' => [],
        ];

        $id = $this->actingAs($this->admin)->postJson('/api/v1/admin/announcements', $payload)->assertCreated()->json('data.id');
        $this->putJson("/api/v1/admin/announcements/{$id}", $payload)->assertOk();

        $this->assertSame(1, PlatformNotification::where('type', 'announcement')->count());
        $this->assertSame(1, $this->actingAs($this->esa)->getJson('/api/v1/me/notifications/unread-count')->json('data.count'));
        $this->assertSame(0, $this->actingAs($this->enem)->getJson('/api/v1/me/notifications/unread-count')->json('data.count'));
    }

    public function test_scheduled_announcement_notifies_only_when_it_goes_on_air(): void
    {
        $announcement = $this->announcement(['send_notification' => true, 'published_at' => now()->addHour()]);

        app(AnnouncementBoard::class)->notifyDue();
        $this->assertSame(0, PlatformNotification::count());

        $this->travel(2)->hours();
        app(AnnouncementBoard::class)->notifyDue();
        app(AnnouncementBoard::class)->notifyDue();

        $this->assertSame(1, PlatformNotification::count());
        $this->assertNotNull($announcement->fresh()->notified_at);
    }

    public function test_student_cannot_manage_communication(): void
    {
        $this->actingAs($this->enem)->getJson('/api/v1/admin/announcements')->assertForbidden();
        $this->postJson('/api/v1/admin/notifications', ['title' => 'x', 'body' => 'y', 'target' => 'all'])->assertForbidden();
    }

    public function test_published_material_notifies_only_who_can_see_it_and_only_once(): void
    {
        $material = $this->material('Conjuntos', $this->esaMath);

        MaterialPublished::dispatch($material);
        MaterialPublished::dispatch($material);

        $this->assertSame(1, PlatformNotification::count());
        $this->assertDatabaseHas('notification_recipients', ['user_id' => $this->esa->id]);
        $this->assertDatabaseMissing('notification_recipients', ['user_id' => $this->enem->id]);
        $this->actingAs($this->esa)->getJson('/api/v1/me/notifications')->assertJsonPath('data.0.url', '/materiais/conjuntos');
    }

    public function test_live_notification_follows_schedule_course_and_option(): void
    {
        $service = app(LiveStreamService::class);
        $make = fn (bool $notify) => LiveSchedule::create(['title' => 'Aula', 'youtube_url' => 'https://www.youtube.com/live/abc123DEF45', 'course_id' => $this->enemCourse->id, 'status' => 'scheduled', 'starts_at' => now(), 'ends_at' => now()->addHour(), 'notify_on_start' => $notify, 'created_by' => $this->admin->id]);

        $stream = $service->start($make(false), $this->admin);
        $this->assertSame(0, PlatformNotification::count());
        $service->end($stream, $this->admin);

        $service->start($make(true), $this->admin);
        $this->assertDatabaseHas('notification_recipients', ['user_id' => $this->enem->id]);
        $this->assertDatabaseMissing('notification_recipients', ['user_id' => $this->esa->id]);
    }

    public function test_admin_sends_by_target(): void
    {
        $group = StudentGroup::create(['name' => 'Turma B', 'slug' => 'turma-b', 'kind' => 'class']);
        $group->students()->attach($this->esa->student);
        $this->actingAs($this->admin);

        $send = fn (string $target, array $ids = []) => $this->postJson('/api/v1/admin/notifications', ['title' => $target, 'body' => 'Mensagem', 'target' => $target, 'ids' => $ids])->assertCreated()->json('data.id');
        $count = fn (int $id) => NotificationRecipient::where('notification_id', $id)->pluck('user_id')->sort()->values()->all();

        $this->assertEqualsCanonicalizing([$this->enem->id, $this->esa->id], $count($send('all')));
        $this->assertSame([$this->enem->id], $count($send('course', [$this->enemCourse->id])));
        $this->assertSame([$this->esa->id], $count($send('group', [$group->id])));
        $this->assertSame([$this->esa->id], $count($send('user', [$this->esa->id])));

        $this->postJson('/api/v1/admin/notifications', ['title' => 'x', 'body' => 'y', 'target' => 'course', 'ids' => []])->assertUnprocessable();
        $this->postJson('/api/v1/admin/notifications', ['title' => 'x', 'body' => 'y', 'target' => 'all', 'url' => '//malicioso.example'])->assertUnprocessable();
    }

    public function test_student_only_touches_own_notifications(): void
    {
        $id = $this->actingAs($this->admin)->postJson('/api/v1/admin/notifications', ['title' => 'Oi', 'body' => 'Mensagem', 'target' => 'user', 'ids' => [$this->esa->id]])->json('data.id');
        $row = NotificationRecipient::where('notification_id', $id)->firstOrFail();

        $this->actingAs($this->enem)->postJson("/api/v1/me/notifications/{$row->id}/read")->assertNotFound();
        $this->actingAs($this->esa)->postJson("/api/v1/me/notifications/{$row->id}/read")->assertNoContent();
        $this->assertNotNull($row->fresh()->read_at);

        $this->postJson('/api/v1/me/notifications/read-all')->assertNoContent();
        $this->assertSame(0, $this->getJson('/api/v1/me/notifications/unread-count')->json('data.count'));
    }

    public function test_search_groups_by_type_and_finds_by_every_field(): void
    {
        $this->material('Revolução Industrial em vídeo', $this->enemMath, ['type' => MaterialType::ExternalVideo]);
        $this->material('Resumo da Revolução Industrial', $this->enemMath);
        $tagged = $this->material('Fábricas', $this->enemMath, ['description' => 'máquinas a vapor']);
        $tagged->tags()->create(['name' => 'Inglaterra', 'slug' => 'inglaterra']);
        $this->material('Só para ESA Industrial', $this->esaMath);

        $this->actingAs($this->enem);
        $response = $this->getJson('/api/v1/search?q=revolu%C3%A7%C3%A3o')->assertOk();

        $this->assertSame(['videos', 'pdfs'], array_column($response->json('data.groups'), 'key'));
        $this->assertSame(2, $response->json('data.total'));

        $titles = fn (string $query) => collect($this->getJson('/api/v1/search?'.$query)->json('data.groups'))->flatMap(fn ($group) => array_column($group['items'], 'title'))->all();

        $this->assertSame(['Fábricas'], $titles('q=vapor'));
        $this->assertSame(['Fábricas'], $titles('q=inglaterra'));
        $this->assertSame(['Revolução Industrial em vídeo'], $titles('q=videoaula'));
        $this->assertCount(3, $titles('q=helena'));
        $this->assertCount(3, $titles('q=matem%C3%A1tica'));
        $this->assertSame([], $titles('q=100%25'));
        $this->assertNotContains('Só para ESA Industrial', $titles('q=industrial'));
        $this->assertSame(['Resumo da Revolução Industrial'], $titles('q=industrial&type=pdf'));
        $this->assertSame('Helena Prado', $this->getJson('/api/v1/search?q=helena')->json('data.teachers.0.name'));
    }

    public function test_search_needs_two_characters(): void
    {
        $this->actingAs($this->enem)->getJson('/api/v1/search?q=a')->assertUnprocessable();
    }
}
