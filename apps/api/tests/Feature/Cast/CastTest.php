<?php

namespace Tests\Feature\Cast;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialService;
use App\Modules\Materials\Services\MaterialWorkflow;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Uploads\Models\Upload;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class CastTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private Teacher $teacher;
    private Category $history;
    private Category $math;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, CatalogSeeder::class]);
        $this->admin = $this->user(Role::ADMIN);
        $this->student = $this->user(Role::STUDENT);
        Student::create(['user_id' => $this->student->id]);
        $this->teacher = Teacher::create(['user_id' => $this->user(Role::TEACHER)->id, 'slug' => 'prof', 'display_name' => 'Prof']);
        $this->history = Category::where('slug', 'historia')->first();
        $this->math = Category::where('slug', 'matematica')->first();
        $this->teacher->categories()->attach($this->history);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->first());

        return $user;
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    private function upload(string $class = 'pdf'): Upload
    {
        return Upload::create([
            'uuid' => (string) Str::uuid(), 'disk' => 'uploads_private', 'object_key' => 'x', 'purpose' => 'material',
            'file_class' => $class, 'original_name' => 'x', 'status' => 'ready', 'uploaded_by' => $this->teacher->user_id,
        ]);
    }

    private function published(string $title, string $type = 'pdf', array $overrides = []): Material
    {
        $material = app(MaterialService::class)->save(null, $overrides + [
            'title' => $title, 'type' => $type, 'visibility' => 'all', 'teacher_id' => $this->teacher->id,
            'file_upload_id' => $this->upload($type)->id, 'category_ids' => [$this->history->id],
        ], $this->admin);

        return app(MaterialWorkflow::class)->publish($material, $this->admin);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['title' => 'Aula', 'type' => 'pdf', 'visibility' => 'all', 'file_upload_id' => $this->upload()->id, 'category_ids' => [$this->history->id]];
    }

    public function test_cast_lists_only_active_visible_teachers(): void
    {
        Teacher::create(['slug' => 'oculto', 'display_name' => 'Oculto', 'show_in_cast' => false]);
        Teacher::create(['slug' => 'inativo', 'display_name' => 'Inativo', 'status' => 'inactive']);

        $this->as($this->student)->getJson('/api/v1/teachers')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Prof')
            ->assertJsonPath('data.0.subjects.0', 'História');

        $this->getJson('/api/v1/teachers/oculto')->assertNotFound();
    }

    public function test_profile_separates_videos_materials_and_only_shows_available_content(): void
    {
        $this->published('Videoaula', 'video');
        $this->published('Apostila');
        $this->published('Interno', 'pdf', ['visibility' => 'staff']);

        $this->as($this->student)->getJson('/api/v1/teachers/prof')->assertOk()
            ->assertJsonPath('data.teacher.name', 'Prof')
            ->assertJsonCount(1, 'data.videos')
            ->assertJsonPath('data.videos.0.title', 'Videoaula')
            ->assertJsonCount(1, 'data.materials')
            ->assertJsonPath('data.materials.0.title', 'Apostila');
    }

    public function test_collaboration_appears_on_the_collaborator_profile(): void
    {
        $other = Teacher::create(['slug' => 'outro', 'display_name' => 'Outro']);
        $this->published('Conjunta', 'pdf', ['collaborator_ids' => [$other->id]]);

        $this->as($this->student)->getJson('/api/v1/teachers/outro')->assertJsonPath('data.materials.0.title', 'Conjunta');
    }

    public function test_admin_creates_teacher_with_account_and_invitation(): void
    {
        Notification::fake();

        $id = $this->as($this->admin)->postJson('/api/v1/admin/teachers', [
            'name' => 'Nova Professora',
            'email' => 'nova@olavo.test',
            'short_bio' => 'Matemática',
            'category_ids' => [$this->math->id],
            'socials' => [['network' => 'youtube', 'url' => 'https://www.youtube.com/@nova'], ['network' => 'site', 'url' => '']],
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'nova-professora')
            ->assertJsonPath('data.email', 'nova@olavo.test')
            ->assertJsonCount(1, 'data.socials')
            ->json('data.id');

        $user = User::where('email', 'nova@olavo.test')->first();
        $this->assertTrue($user->roles->contains('slug', Role::TEACHER));
        $this->assertSame($user->id, Teacher::find($id)->user_id);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_email_cannot_repeat_and_social_url_must_be_https(): void
    {
        $this->as($this->admin)->postJson('/api/v1/admin/teachers', ['name' => 'X', 'email' => $this->student->email])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/admin/teachers', ['name' => 'X', 'socials' => [['network' => 'site', 'url' => 'http://inseguro.test']]])
            ->assertStatus(422);
    }

    public function test_teacher_with_materials_cannot_be_deleted(): void
    {
        $this->published('Aula');

        $this->as($this->admin)->deleteJson("/api/v1/admin/teachers/{$this->teacher->id}")
            ->assertStatus(409)->assertJsonPath('code', 'teacher_has_materials');

        $free = Teacher::create(['slug' => 'livre', 'display_name' => 'Livre']);
        $this->deleteJson("/api/v1/admin/teachers/{$free->id}")->assertNoContent();
        $this->assertSoftDeleted($free);
    }

    public function test_only_admin_manages_teachers(): void
    {
        $this->as($this->teacher->user)->getJson('/api/v1/admin/teachers')->assertForbidden();
        $this->as($this->student)->getJson('/api/v1/admin/teachers')->assertForbidden();
    }

    public function test_student_cannot_enter_teacher_panel(): void
    {
        $this->as($this->student)->getJson('/api/v1/teacher/overview')->assertForbidden();
    }

    public function test_default_policy_sends_teacher_publication_to_review(): void
    {
        $id = $this->as($this->teacher->user)->postJson('/api/v1/teacher/materials', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.teacher.name', 'Prof')
            ->json('data.id');

        $this->postJson("/api/v1/teacher/materials/{$id}/release")->assertOk()->assertJsonPath('data.status', 'pending_review');
    }

    public function test_global_policy_can_allow_immediate_publication(): void
    {
        app(SettingsService::class)->set(Teacher::PUBLISH_SETTING, 'immediate');

        $id = $this->as($this->teacher->user)->postJson('/api/v1/teacher/materials', $this->payload())->json('data.id');

        $this->getJson('/api/v1/teacher/overview')->assertJsonPath('data.can_publish', true);
        $this->postJson("/api/v1/teacher/materials/{$id}/release")->assertJsonPath('data.status', 'published');
    }

    public function test_teacher_rule_overrides_global_policy(): void
    {
        app(SettingsService::class)->set(Teacher::PUBLISH_SETTING, 'immediate');
        $this->teacher->update(['publish_mode' => 'approval']);

        $id = $this->as($this->teacher->user)->postJson('/api/v1/teacher/materials', $this->payload())->json('data.id');

        $this->postJson("/api/v1/teacher/materials/{$id}/release")->assertJsonPath('data.status', 'pending_review');
    }

    public function test_admin_changes_global_policy(): void
    {
        $this->as($this->admin)->putJson('/api/v1/admin/teachers/publish-policy', ['mode' => 'immediate'])->assertOk();

        $this->getJson('/api/v1/admin/teachers/publish-policy')->assertJsonPath('data.mode', 'immediate');
    }

    public function test_teacher_publishes_only_in_authorized_categories(): void
    {
        $this->as($this->teacher->user)
            ->postJson('/api/v1/teacher/materials', $this->payload(['category_ids' => [$this->math->id]]))
            ->assertStatus(422)->assertJsonValidationErrors('category_ids');

        $this->getJson('/api/v1/teacher/categories')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'História');
    }

    public function test_teacher_cannot_touch_material_of_another_teacher(): void
    {
        $other = Teacher::create(['slug' => 'outro', 'display_name' => 'Outro']);
        $foreign = $this->published('Alheio', 'pdf', ['teacher_id' => $other->id]);

        $this->as($this->teacher->user);

        $this->getJson("/api/v1/teacher/materials/{$foreign->id}")->assertForbidden();
        $this->putJson("/api/v1/teacher/materials/{$foreign->id}", $this->payload())->assertForbidden();
        $this->deleteJson("/api/v1/teacher/materials/{$foreign->id}")->assertForbidden();
        $this->postJson("/api/v1/teacher/materials/{$foreign->id}/release")->assertForbidden();
        $this->assertSame('Alheio', $foreign->fresh()->title);
    }

    public function test_teacher_cannot_mark_as_featured_or_reassign_authorship(): void
    {
        $other = Teacher::create(['slug' => 'outro', 'display_name' => 'Outro']);

        $id = $this->as($this->teacher->user)->postJson('/api/v1/teacher/materials', $this->payload([
            'is_featured' => true,
            'teacher_id' => $other->id,
        ]))->assertCreated()->json('data.id');

        $material = Material::find($id);
        $this->assertFalse($material->is_featured);
        $this->assertSame($this->teacher->id, $material->teacher_id);
    }

    public function test_collaborator_edits_without_taking_authorship(): void
    {
        $other = Teacher::create(['user_id' => $this->user(Role::TEACHER)->id, 'slug' => 'outro', 'display_name' => 'Outro']);
        $other->categories()->attach($this->history);
        $material = $this->published('Conjunta', 'pdf', ['collaborator_ids' => [$other->id]]);

        $this->as($other->user)->putJson("/api/v1/teacher/materials/{$material->id}", $this->payload(['title' => 'Editada', 'collaborator_ids' => [$other->id]]))->assertOk();

        $this->assertSame($this->teacher->id, $material->fresh()->teacher_id);
        $this->assertSame('Editada', $material->fresh()->title);
    }

    public function test_teacher_deletes_only_own_drafts(): void
    {
        $id = $this->as($this->teacher->user)->postJson('/api/v1/teacher/materials', $this->payload())->json('data.id');
        $published = $this->published('Publicado');

        $this->deleteJson("/api/v1/teacher/materials/{$published->id}")->assertForbidden();
        $this->deleteJson("/api/v1/teacher/materials/{$id}")->assertNoContent();
    }

    public function test_overview_counts_only_own_materials_and_views(): void
    {
        $own = $this->published('Minha');
        $other = Teacher::create(['slug' => 'outro', 'display_name' => 'Outro']);
        $foreign = $this->published('Alheia', 'pdf', ['teacher_id' => $other->id]);
        $this->as($this->student)->postJson("/api/v1/materials/{$own->slug}/access");
        $this->postJson("/api/v1/materials/{$foreign->slug}/access");

        $this->as($this->teacher->user)->getJson('/api/v1/teacher/overview')->assertOk()
            ->assertJsonPath('data.totals.published', 1)
            ->assertJsonPath('data.totals.views', 1)
            ->assertJsonPath('data.totals.students', 1)
            ->assertJsonPath('data.top_materials.0.title', 'Minha');
    }

    public function test_teacher_list_shows_view_count(): void
    {
        $own = $this->published('Minha');
        $this->as($this->student)->postJson("/api/v1/materials/{$own->slug}/access");

        $this->as($this->teacher->user)->getJson('/api/v1/teacher/materials')->assertJsonPath('data.0.views_count', 1);
    }

    public function test_teacher_updates_own_profile_but_not_permissions(): void
    {
        $this->as($this->teacher->user)->putJson('/api/v1/teacher/profile', [
            'short_bio' => 'Nova descrição',
            'specialties' => ['Era Vargas'],
            'publish_mode' => 'immediate',
            'show_in_cast' => false,
            'socials' => [['network' => 'instagram', 'url' => 'https://www.instagram.com/prof']],
        ])->assertOk()->assertJsonPath('data.short_bio', 'Nova descrição');

        $fresh = $this->teacher->fresh();
        $this->assertSame('inherit', $fresh->publish_mode);
        $this->assertTrue($fresh->show_in_cast);
        $this->assertCount(1, $fresh->socialLinks);
    }
}
