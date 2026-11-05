<?php

namespace Tests\Feature\People;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Communication\Models\NotificationRecipient;
use App\Modules\Communication\Models\PlatformNotification;
use App\Modules\Communication\Services\NotificationCenter;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Events\MaterialPublished;
use App\Modules\Materials\Models\Material;
use App\Modules\Settings\Services\SettingsService;
use App\Support\Audit\ActivityLog;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CourseSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PeopleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, SettingsSeeder::class, CatalogSeeder::class, CourseSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', Role::ADMIN)->value('id'));
        $this->course = Course::where('slug', 'preparatorio-esa')->firstOrFail();
    }

    private function student(array $attributes = []): Student
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::where('slug', Role::STUDENT)->value('id'));

        return Student::create(['user_id' => $user->id]);
    }

    private function payload(array $overrides = []): array
    {
        return ['name' => 'Nova Aluna', 'email' => 'nova@olavo.test', 'phone' => '55999990000', 'enrollment_code' => 'A-1', 'birth_date' => '2008-01-02', 'guardian_name' => null, 'guardian_contact' => null, 'course_ids' => [$this->course->id], 'group_ids' => [], ...$overrides];
    }

    public function test_admin_registers_student_who_receives_password_link(): void
    {
        Notification::fake();

        $id = $this->actingAs($this->admin)->postJson('/api/v1/admin/students', $this->payload())->assertCreated()->json('data.id');

        $student = Student::findOrFail($id);
        $this->assertTrue($student->user->roles->contains('slug', Role::STUDENT));
        $this->assertSame([$this->course->id], $student->courses->modelKeys());
        Notification::assertSentTo($student->user, ResetPassword::class);

        $this->postJson('/api/v1/admin/students', $this->payload(['enrollment_code' => null]))->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_admin_updates_student_links_and_searches(): void
    {
        $student = $this->student(['name' => 'Antes']);
        $group = StudentGroup::create(['name' => 'Turma', 'slug' => 'turma', 'kind' => 'turma']);

        $this->actingAs($this->admin)->putJson("/api/v1/admin/students/{$student->id}", $this->payload(['group_ids' => [$group->id]]))->assertOk()->assertJsonPath('data.name', 'Nova Aluna');

        $this->assertSame(1, $this->getJson('/api/v1/admin/students?q=nova')->json('meta.total'));
        $this->assertSame(1, $this->getJson("/api/v1/admin/students?course={$this->course->id}")->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/v1/admin/students?q=inexistente')->json('meta.total'));
    }

    public function test_blocking_cuts_access_and_unblocking_restores_it(): void
    {
        $student = $this->student();

        $this->actingAs($this->admin)->postJson("/api/v1/admin/students/{$student->id}/block")->assertNoContent();
        $this->assertSame(UserStatus::Blocked, $student->user->fresh()->status);
        $this->app['auth']->forgetGuards();
        $this->actingAs($student->user->fresh())->getJson('/api/v1/me')->assertForbidden();

        $this->actingAs($this->admin)->postJson("/api/v1/admin/students/{$student->id}/unblock")->assertNoContent();
        $this->assertSame(UserStatus::Active, $student->user->fresh()->status);
    }

    public function test_student_cannot_reach_people_or_reports(): void
    {
        $student = $this->student();

        $this->actingAs($student->user);
        $this->getJson('/api/v1/admin/students')->assertForbidden();
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
        $this->getJson('/api/v1/admin/audit')->assertForbidden();
        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->postJson("/api/v1/admin/students/{$student->id}/anonymize")->assertForbidden();
    }

    public function test_group_crud(): void
    {
        $this->actingAs($this->admin);
        $id = $this->postJson('/api/v1/admin/groups', ['name' => 'Extensivo', 'kind' => 'turma', 'year' => 2026, 'is_active' => true])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/admin/groups', ['name' => 'Extensivo', 'kind' => 'turma', 'year' => null, 'is_active' => true])->assertCreated()->assertJsonPath('data.slug', 'extensivo-2');
        $this->putJson("/api/v1/admin/groups/{$id}", ['name' => 'Intensivo', 'kind' => 'grupo', 'year' => null, 'is_active' => false])->assertOk();
        $this->deleteJson("/api/v1/admin/groups/{$id}")->assertNoContent();
        $this->assertCount(1, $this->getJson('/api/v1/admin/groups')->json('data'));
    }

    public function test_dashboard_counts_and_fills_days(): void
    {
        Cache::flush();
        $this->student();

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/dashboard')->assertOk();

        $this->assertSame(1, $response->json('data.totals.students'));
        $this->assertCount(30, $response->json('data.views_by_day'));
        $this->assertSame(now()->toDateString(), $response->json('data.views_by_day.29.day'));
    }

    public function test_audit_lists_and_filters(): void
    {
        $student = $this->student();
        $this->actingAs($this->admin)->postJson("/api/v1/admin/students/{$student->id}/block")->assertNoContent();

        $response = $this->getJson('/api/v1/admin/audit?action=student.blocked')->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($this->admin->name, $response->json('data.0.actor'));
        $this->assertContains('student.blocked', $response->json('actions'));
    }

    public function test_settings_are_public_where_expected_and_hide_private(): void
    {
        $body = ['name' => 'Olavo', 'tagline' => null, 'footer' => 'Rodapé', 'brand_color' => '#ffaa00', 'logo_upload_id' => null, 'socials' => ['instagram' => 'https://instagram.com/olavo'], 'terms' => null, 'privacy' => null, 'auto_material' => false, 'auto_live' => true];

        $this->actingAs($this->admin)->putJson('/api/v1/admin/settings', $body)->assertOk()->assertJsonPath('data.footer', 'Rodapé');
        $this->putJson('/api/v1/admin/settings', [...$body, 'brand_color' => 'vermelho'])->assertUnprocessable();

        $public = $this->getJson('/api/v1/config')->json('data');
        $this->assertSame('#ffaa00', $public['platform.brand_color']);
        $this->assertArrayNotHasKey('notifications.auto_material', $public);
    }

    public function test_automatic_notification_can_be_turned_off(): void
    {
        $student = $this->student();
        $student->courses()->attach($this->course);
        $category = Category::where('course_id', $this->course->id)->firstOrFail();
        $material = Material::create(['slug' => 'aula', 'title' => 'Aula', 'type' => MaterialType::Pdf, 'status' => MaterialStatus::Published, 'visibility' => 'all', 'published_at' => now()->subMinute()]);
        $material->categories()->attach($category, ['is_primary' => true]);

        app(SettingsService::class)->set('notifications.auto_material', false);
        MaterialPublished::dispatch($material);
        $this->assertSame(0, PlatformNotification::count());

        app(SettingsService::class)->set('notifications.auto_material', true);
        MaterialPublished::dispatch($material);
        $this->assertSame(1, NotificationRecipient::where('user_id', $student->user_id)->count());
    }

    public function test_changed_terms_ask_for_new_acceptance(): void
    {
        $student = $this->student();
        $this->actingAs($student->user)->getJson('/api/v1/me')->assertJsonPath('data.terms_pending', false);

        $body = ['name' => 'Olavo', 'tagline' => null, 'footer' => null, 'brand_color' => null, 'logo_upload_id' => null, 'socials' => [], 'terms' => 'Termos 1', 'privacy' => null, 'auto_material' => true, 'auto_live' => true];
        $this->actingAs($this->admin)->putJson('/api/v1/admin/settings', $body)->assertOk();

        $this->actingAs($student->user)->getJson('/api/v1/me')->assertJsonPath('data.terms_pending', true);
        $this->postJson('/api/v1/me/terms/accept')->assertNoContent();
        $this->getJson('/api/v1/me')->assertJsonPath('data.terms_pending', false);

        $this->travel(2)->seconds();
        $this->actingAs($this->admin)->putJson('/api/v1/admin/settings', [...$body, 'terms' => 'Termos 2'])->assertOk();
        $this->actingAs($student->user->fresh())->getJson('/api/v1/me')->assertJsonPath('data.terms_pending', true);
    }

    public function test_student_exports_own_data_and_requests_deletion(): void
    {
        $student = $this->student(['name' => 'Ana Titular']);
        $student->courses()->attach($this->course);

        $this->actingAs($student->user)->getJson('/api/v1/me/export')->assertOk()
            ->assertJsonPath('data.conta.nome', 'Ana Titular')
            ->assertJsonPath('data.aluno.cursos.0', $this->course->name);

        $this->postJson('/api/v1/me/deletion-request')->assertNoContent();
        $this->actingAs($this->admin)->getJson('/api/v1/admin/students?deletion=1')->assertJsonPath('meta.total', 1);
    }

    public function test_anonymize_removes_personal_data_and_keeps_history(): void
    {
        $student = $this->student(['name' => 'Ana Titular', 'email' => 'ana@olavo.test', 'phone' => '559999']);
        $student->update(['enrollment_code' => 'X1', 'guardian_name' => 'Responsável']);
        $student->courses()->attach($this->course);
        ActivityLog::create(['actor_id' => $student->user_id, 'action' => 'auth.login', 'ip_address' => '10.0.0.1']);
        $user = $student->user;

        $this->actingAs($this->admin)->postJson("/api/v1/admin/students/{$student->id}/anonymize")->assertNoContent();

        $fresh = User::withTrashed()->findOrFail($user->id);
        $this->assertSame('Aluno removido', $fresh->name);
        $this->assertSame("removido-{$user->id}@anonimo.invalid", $fresh->email);
        $this->assertNull($fresh->phone);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertNull(Student::withTrashed()->find($student->id)->guardian_name);
        $this->assertSame(0, Student::withTrashed()->find($student->id)->courses()->count());
        $this->assertNull(ActivityLog::where('actor_id', $user->id)->first()->ip_address);
        $this->getJson('/api/v1/admin/students')->assertJsonPath('meta.total', 0);
        $this->postJson('/api/v1/auth/login', ['email' => 'ana@olavo.test', 'password' => 'password'])->assertStatus(422);
    }

    public function test_muted_type_does_not_reach_student_but_manual_does(): void
    {
        $student = $this->student();
        $student->user->update(['preferences' => ['muted_notifications' => ['material']]]);
        $center = app(NotificationCenter::class);

        $muted = $center->send('material', 'Novo', 'Algo', null, ['mode' => 'all']);
        $manual = $center->send('manual', 'Aviso', 'Algo', null, ['mode' => 'all']);

        $this->assertSame(0, NotificationRecipient::where('notification_id', $muted->id)->count());
        $this->assertSame(1, NotificationRecipient::where('notification_id', $manual->id)->count());
    }

    public function test_api_sends_security_headers(): void
    {
        $this->getJson('/api/v1/config')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
    }
}
