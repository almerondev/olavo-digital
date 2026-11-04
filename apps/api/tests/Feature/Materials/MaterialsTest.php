<?php

namespace Tests\Feature\Materials;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Events\MaterialPublished;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialAccessService;
use App\Modules\Materials\Services\MaterialService;
use App\Modules\Materials\Services\MaterialWorkflow;
use App\Modules\Uploads\Models\Upload;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class MaterialsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Teacher $teacher;
    private Category $history;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, CatalogSeeder::class]);
        $this->admin = $this->userWith(Role::ADMIN);
        $this->teacher = Teacher::create(['user_id' => $this->userWith(Role::TEACHER)->id, 'slug' => 'prof', 'display_name' => 'Prof']);
        $this->history = Category::where('slug', 'historia')->first();
    }

    private function userWith(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->first());

        return $user;
    }

    private function student(?StudentGroup $group = null): User
    {
        $user = $this->userWith(Role::STUDENT);
        $student = Student::create(['user_id' => $user->id]);
        $group?->students()->attach($student);

        return $user->fresh();
    }

    private function readyUpload(string $class = 'pdf'): Upload
    {
        return Upload::create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'uploads_private',
            'object_key' => 'materials/x.pdf',
            'purpose' => 'material',
            'file_class' => $class,
            'original_name' => 'x.pdf',
            'status' => 'ready',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Revolução Industrial',
            'type' => 'pdf',
            'visibility' => 'all',
            'teacher_id' => $this->teacher->id,
            'file_upload_id' => $this->readyUpload()->id,
            'category_ids' => [$this->history->id],
            'tags' => ['Século XVIII', 'Inglaterra'],
        ];
    }

    private function published(array $overrides = []): Material
    {
        $material = app(MaterialService::class)->save(null, $this->payload($overrides), $this->admin);

        return app(MaterialWorkflow::class)->publish($material, $this->admin);
    }

    public function test_admin_creates_a_draft_with_categories_tags_and_file(): void
    {
        $response = $this->actingAs($this->admin, 'web')->postJson('/api/v1/admin/materials', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.slug', 'revolucao-industrial')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.categories.0.is_primary', true)
            ->assertJsonPath('data.tags.0', 'Século XVIII');

        $this->assertDatabaseHas('material_files', ['material_id' => $response->json('data.id'), 'role' => 'primary']);
    }

    public function test_material_belongs_to_many_categories_with_one_primary(): void
    {
        $math = Category::where('slug', 'matematica')->first();
        $material = app(MaterialService::class)->save(null, $this->payload([
            'category_ids' => [$this->history->id, $math->id],
            'primary_category_id' => $math->id,
        ]), $this->admin);

        $this->assertSame($math->id, $material->categories()->wherePivot('is_primary', true)->first()->id);

        // Trocar a principal não viola o índice único.
        app(MaterialService::class)->save($material, $this->payload([
            'category_ids' => [$this->history->id, $math->id],
            'primary_category_id' => $this->history->id,
        ]), $this->admin);

        $this->assertSame($this->history->id, $material->categories()->wherePivot('is_primary', true)->first()->id);
    }

    public function test_file_must_match_material_type(): void
    {
        $this->actingAs($this->admin, 'web')
            ->postJson('/api/v1/admin/materials', $this->payload(['type' => 'audio']))
            ->assertStatus(422)->assertJsonValidationErrors('file_upload_id');
    }

    public function test_external_video_registers_provider(): void
    {
        $material = app(MaterialService::class)->save(null, $this->payload([
            'type' => 'external_video',
            'external_url' => 'https://www.youtube.com/watch?v=abc',
            'file_upload_id' => null,
        ]), $this->admin);

        $this->assertSame('youtube', $material->videoAsset->provider);
        $this->assertCount(0, $material->files);
    }

    public function test_incomplete_material_cannot_be_published(): void
    {
        $material = Material::create(['slug' => 'vazio', 'title' => 'Vazio', 'type' => 'pdf']);

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/materials/{$material->id}/release")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['teacher_id', 'category_ids', 'file_upload_id']);
    }

    public function test_file_still_processing_blocks_publication(): void
    {
        $upload = $this->readyUpload();
        $upload->update(['status' => 'processing']);
        $material = app(MaterialService::class)->save(null, $this->payload(['file_upload_id' => $upload->id]), $this->admin);

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/materials/{$material->id}/release")
            ->assertStatus(422)->assertJsonValidationErrors('file_upload_id');
    }

    public function test_publishing_dispatches_event_once_even_after_archive_and_restore(): void
    {
        Event::fake([MaterialPublished::class]);
        $material = app(MaterialService::class)->save(null, $this->payload(), $this->admin);
        $this->actingAs($this->admin, 'web');

        $this->postJson("/api/v1/admin/materials/{$material->id}/release")->assertOk()->assertJsonPath('data.status', 'published');
        $this->postJson("/api/v1/admin/materials/{$material->id}/archive")->assertOk()->assertJsonPath('data.status', 'archived');
        $this->postJson("/api/v1/admin/materials/{$material->id}/restore")->assertOk()->assertJsonPath('data.status', 'published');

        Event::assertDispatchedTimes(MaterialPublished::class, 1);
    }

    public function test_invalid_transition_returns_409(): void
    {
        $material = Material::create(['slug' => 'r', 'title' => 'R', 'type' => 'pdf']);

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/materials/{$material->id}/archive")
            ->assertStatus(409)->assertJsonPath('code', 'invalid_transition');
    }

    public function test_scheduled_material_is_published_by_the_command_only_when_due(): void
    {
        Event::fake([MaterialPublished::class]);
        $material = app(MaterialService::class)->save(null, $this->payload(['publish_at' => now()->addHour()->toDateTimeString()]), $this->admin);

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/materials/{$material->id}/release")
            ->assertOk()->assertJsonPath('data.status', 'scheduled');

        $this->artisan('materials:publish-scheduled')->assertSuccessful();
        $this->assertSame(MaterialStatus::Scheduled, $material->fresh()->status);

        $this->travel(2)->hours();
        $this->artisan('materials:publish-scheduled')->assertSuccessful();
        $this->assertSame(MaterialStatus::Published, $material->fresh()->status);
        Event::assertDispatchedTimes(MaterialPublished::class, 1);
    }

    public function test_scheduled_material_that_became_incomplete_returns_to_draft(): void
    {
        $material = app(MaterialService::class)->save(null, $this->payload(['publish_at' => now()->addHour()->toDateTimeString()]), $this->admin);
        app(MaterialWorkflow::class)->release($material, $this->admin);
        $material->files()->delete();

        $this->travel(2)->hours();
        $this->artisan('materials:publish-scheduled')->assertSuccessful();

        $this->assertSame(MaterialStatus::Draft, $material->fresh()->status);
        $this->assertNotNull($material->fresh()->review_note);
    }

    public function test_teacher_without_publish_permission_goes_to_review_and_admin_decides(): void
    {
        $material = app(MaterialService::class)->save(null, $this->payload(), $this->admin);
        $workflow = app(MaterialWorkflow::class);

        $workflow->release($material, $this->teacher->user);
        $this->assertSame(MaterialStatus::PendingReview, $material->status);

        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/materials/{$material->id}/reject")
            ->assertStatus(422)->assertJsonValidationErrors('note');

        $this->postJson("/api/v1/admin/materials/{$material->id}/reject", ['note' => 'Ajuste a capa.'])
            ->assertOk()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.review_note', 'Ajuste a capa.');

        $workflow->release($material->fresh(), $this->teacher->user);

        $this->postJson("/api/v1/admin/materials/{$material->id}/approve")->assertOk()->assertJsonPath('data.status', 'published');
    }

    public function test_teacher_only_edits_own_material_and_publishes_in_authorized_categories(): void
    {
        $other = Teacher::create(['user_id' => $this->userWith(Role::TEACHER)->id, 'slug' => 'outro', 'display_name' => 'Outro']);
        $foreign = app(MaterialService::class)->save(null, $this->payload(['teacher_id' => $other->id]), $this->admin);
        $own = app(MaterialService::class)->save(null, $this->payload(), $this->admin);
        $user = $this->teacher->user;

        $this->assertFalse($user->can('update', $foreign));
        $this->assertTrue($user->can('update', $own));
        $this->assertFalse($user->can('publish', $own));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MaterialService::class)->save(null, $this->payload(), $user);
    }

    public function test_collaborator_can_edit_material(): void
    {
        $other = Teacher::create(['user_id' => $this->userWith(Role::TEACHER)->id, 'slug' => 'outro', 'display_name' => 'Outro']);
        $material = app(MaterialService::class)->save(null, $this->payload(['collaborator_ids' => [$other->id]]), $this->admin);

        $this->assertTrue($other->user->can('update', $material));
    }

    public function test_student_sees_only_what_is_published_and_released_to_them(): void
    {
        $group = StudentGroup::create(['name' => 'Turma A', 'slug' => 'turma-a']);
        $member = $this->student($group);
        $outsider = $this->student();
        $access = app(MaterialAccessService::class);

        $open = $this->published();
        $restricted = $this->published(['visibility' => 'restricted', 'group_ids' => [$group->id]]);
        $staff = $this->published(['visibility' => 'staff']);
        $draft = app(MaterialService::class)->save(null, $this->payload(), $this->admin);

        $this->assertEqualsCanonicalizing([$open->id, $restricted->id], Material::visibleTo($member)->pluck('id')->all());
        $this->assertSame([$open->id], Material::visibleTo($outsider)->pluck('id')->all());

        $this->assertTrue($access->canView($member, $restricted));
        $this->assertFalse($access->canView($outsider, $restricted));
        $this->assertFalse($access->canView($member, $staff));
        $this->assertFalse($access->canView($member, $draft));
        $this->assertTrue($access->canView($this->admin, $draft));
    }

    public function test_inactive_or_restricted_category_hides_material(): void
    {
        $group = StudentGroup::create(['name' => 'Turma A', 'slug' => 'turma-a']);
        $member = $this->student($group);
        $outsider = $this->student();
        $material = $this->published();

        $this->history->update(['is_active' => false]);
        $this->assertFalse(Material::visibleTo($member)->exists());

        $this->history->update(['is_active' => true, 'visibility' => 'restricted']);
        $this->history->syncAudience([$group->id]);

        $this->assertTrue(Material::visibleTo($member)->whereKey($material->id)->exists());
        $this->assertFalse(Material::visibleTo($outsider)->exists());
    }

    public function test_individual_student_can_be_targeted(): void
    {
        $student = $this->student();
        $material = $this->published(['visibility' => 'restricted', 'student_ids' => [$student->student->id]]);

        $this->assertTrue(Material::visibleTo($student)->whereKey($material->id)->exists());
    }

    public function test_filters_combine_in_admin_library(): void
    {
        $this->published();
        $math = Category::where('slug', 'matematica')->first();
        app(MaterialService::class)->save(null, $this->payload(['title' => 'Funções', 'category_ids' => [$math->id]]), $this->admin);

        $this->actingAs($this->admin, 'web');

        $this->getJson('/api/v1/admin/materials?status=draft')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Funções');
        $this->getJson("/api/v1/admin/materials?category_id={$this->history->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/materials?q=Revolu')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/materials?teacher_id={$this->teacher->id}&type=pdf")->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/admin/materials?type=audio')->assertJsonCount(0, 'data');
    }

    public function test_bulk_reports_items_that_failed_without_stopping(): void
    {
        $ready = app(MaterialService::class)->save(null, $this->payload(), $this->admin);
        $empty = Material::create(['slug' => 'vazio', 'title' => 'Vazio', 'type' => 'pdf']);

        $this->actingAs($this->admin, 'web')->postJson('/api/v1/admin/materials/bulk', [
            'action' => 'publish',
            'ids' => [$ready->id, $empty->id],
        ])->assertOk()
            ->assertJsonPath('data.processed', 1)
            ->assertJsonPath('data.failed.0.title', 'Vazio');

        $this->assertSame(MaterialStatus::Published, $ready->fresh()->status);
    }

    public function test_bulk_changes_teacher_adds_category_and_deletes(): void
    {
        $other = Teacher::create(['slug' => 'outro', 'display_name' => 'Outro']);
        $math = Category::where('slug', 'matematica')->first();
        $material = app(MaterialService::class)->save(null, $this->payload(), $this->admin);
        $this->actingAs($this->admin, 'web');

        $this->postJson('/api/v1/admin/materials/bulk', ['action' => 'set_teacher', 'ids' => [$material->id], 'teacher_id' => $other->id])->assertOk();
        $this->postJson('/api/v1/admin/materials/bulk', ['action' => 'add_category', 'ids' => [$material->id], 'category_id' => $math->id])->assertOk();
        $this->assertSame($other->id, $material->fresh()->teacher_id);
        $this->assertCount(2, $material->categories()->get());

        $this->postJson('/api/v1/admin/materials/bulk', ['action' => 'delete', 'ids' => [$material->id]])->assertOk();
        $this->assertSoftDeleted($material);
    }

    public function test_duplicate_creates_independent_draft(): void
    {
        $source = $this->published();

        $copy = $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/materials/{$source->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.title', 'Revolução Industrial (cópia)')
            ->json('data');

        $this->assertNotSame($source->slug, $copy['slug']);
        $this->assertCount(1, $copy['categories']);
        $this->assertNull($copy['published_at']);
    }

    public function test_teacher_and_student_are_refused_by_admin_library(): void
    {
        foreach ([$this->teacher->user, $this->student()] as $user) {
            $this->actingAs($user, 'web')->getJson('/api/v1/admin/materials')->assertForbidden();
        }
    }
}
