<?php

namespace Tests\Feature\Home;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Home\Models\HomeLayout;
use App\Modules\Home\Services\HomeService;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialService;
use App\Modules\Materials\Services\MaterialWorkflow;
use App\Modules\Uploads\Models\Upload;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HomeBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private HomeLayout $published;
    private Category $history;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, CatalogSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', Role::ADMIN)->first());
        $this->student = User::factory()->create();
        $this->student->roles()->attach(Role::where('slug', Role::STUDENT)->first());
        Student::create(['user_id' => $this->student->id]);
        $this->history = Category::where('slug', 'historia')->first();
        $this->published = HomeLayout::create(['name' => 'Início', 'surface' => 'home', 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->published->sections()->create(['title' => 'Atual', 'source_type' => 'recent']);
        app(HomeService::class)->flush();
        $this->actingAs($this->admin, 'web');
    }

    private function video(string $title, array $overrides = []): Material
    {
        $upload = Upload::create([
            'uuid' => (string) Str::uuid(), 'disk' => 'uploads_private', 'object_key' => 'v.mp4', 'purpose' => 'material',
            'file_class' => 'video', 'original_name' => 'v.mp4', 'status' => 'ready',
        ]);
        $teacher = Teacher::firstOrCreate(['slug' => 'prof'], ['display_name' => 'Prof']);
        $material = app(MaterialService::class)->save(null, $overrides + [
            'title' => $title, 'type' => 'video', 'visibility' => 'all', 'teacher_id' => $teacher->id,
            'file_upload_id' => $upload->id, 'category_ids' => [$this->history->id],
        ], $this->admin);

        return app(MaterialWorkflow::class)->publish($material, $this->admin);
    }

    private function section(array $overrides = []): array
    {
        return $overrides + ['title' => 'Nova', 'source_type' => 'recent', 'layout' => 'carousel', 'max_items' => 12, 'is_active' => true];
    }

    private function draft(): int
    {
        return $this->postJson('/api/v1/admin/home/draft', ['surface' => 'home'])->assertOk()->json('data.id');
    }

    private function studentHome()
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($this->student, 'web')->getJson('/api/v1/home')->assertOk();
    }

    public function test_draft_copies_published_layout_and_is_reused(): void
    {
        $source = $this->published->sections()->first();
        $source->materials()->attach($this->video('Fixa'));
        $group = StudentGroup::create(['name' => 'T', 'slug' => 't']);
        $source->syncAudience([$group->id]);
        $this->published->banners()->create(['title' => 'Campanha']);

        $response = $this->postJson('/api/v1/admin/home/draft', ['surface' => 'home'])->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.sections.0.title', 'Atual')
            ->assertJsonPath('data.sections.0.materials.0.title', 'Fixa')
            ->assertJsonPath('data.sections.0.group_ids.0', $group->id)
            ->assertJsonPath('data.banners.0.title', 'Campanha');

        $this->assertNotSame($source->id, $response->json('data.sections.0.id'));
        $this->assertSame($response->json('data.id'), $this->draft());
        $this->assertSame(2, HomeLayout::count());
    }

    public function test_changes_stay_hidden_from_students_until_publication(): void
    {
        $this->video('Aula');
        $id = $this->draft();

        $this->postJson("/api/v1/admin/home/layouts/{$id}/sections", $this->section(['title' => 'Da campanha']))->assertCreated();

        $this->studentHome()->assertJsonCount(1, 'data.sections')->assertJsonPath('data.sections.0.title', 'Atual');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin, 'web')->postJson("/api/v1/admin/home/layouts/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'published');

        $titles = array_column($this->studentHome()->json('data.sections'), 'title');
        $this->assertSame(['Atual', 'Da campanha'], $titles);
        $this->assertSame('archived', $this->published->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'home.published']);
    }

    public function test_reorder_defines_the_final_order(): void
    {
        $this->video('Aula');
        $id = $this->draft();
        $second = $this->postJson("/api/v1/admin/home/layouts/{$id}/sections", $this->section(['title' => 'Segunda']))->json('data.id');
        $first = HomeLayout::find($id)->sections()->where('title', 'Atual')->value('id');

        $this->putJson("/api/v1/admin/home/layouts/{$id}/sections/order", ['ids' => [$second, $first]])->assertNoContent();
        $this->postJson("/api/v1/admin/home/layouts/{$id}/publish")->assertOk();

        $this->studentHome()->assertJsonPath('data.sections.0.title', 'Segunda');
    }

    public function test_section_rules_are_validated(): void
    {
        $id = $this->draft();

        $this->postJson("/api/v1/admin/home/layouts/{$id}/sections", $this->section(['source_type' => 'category']))->assertStatus(422)->assertJsonValidationErrors('category_id');
        $this->postJson("/api/v1/admin/home/layouts/{$id}/sections", $this->section(['source_type' => 'desconhecida']))->assertStatus(422)->assertJsonValidationErrors('source_type');
        $this->postJson("/api/v1/admin/home/layouts/{$id}/sections", $this->section(['max_items' => 500]))->assertStatus(422)->assertJsonValidationErrors('max_items');
        $this->postJson("/api/v1/admin/home/layouts/{$id}/sections", $this->section(['starts_at' => '2026-12-10', 'ends_at' => '2026-12-01']))->assertStatus(422)->assertJsonValidationErrors('ends_at');
    }

    public function test_manual_section_keeps_chosen_materials_order(): void
    {
        $one = $this->video('Um');
        $two = $this->video('Dois');
        $id = $this->draft();

        $this->postJson("/api/v1/admin/home/layouts/{$id}/sections", $this->section([
            'title' => 'Escolhidos', 'source_type' => 'manual', 'material_ids' => [$two->id, $one->id],
        ]))->assertCreated()->assertJsonPath('data.materials.0.title', 'Dois');

        $this->postJson("/api/v1/admin/home/layouts/{$id}/publish");

        $this->studentHome()
            ->assertJsonPath('data.sections.1.title', 'Escolhidos')
            ->assertJsonPath('data.sections.1.items.0.title', 'Dois');
    }

    public function test_teacher_section_filters_by_teacher(): void
    {
        $other = Teacher::create(['slug' => 'outro', 'display_name' => 'Outro']);
        $this->video('Da outra', ['teacher_id' => $other->id]);
        $this->video('Da primeira');
        $id = $this->draft();

        $this->postJson("/api/v1/admin/home/layouts/{$id}/sections", $this->section(['title' => 'Do professor', 'source_type' => 'teacher', 'teacher_id' => $other->id]))->assertCreated();
        $this->postJson("/api/v1/admin/home/layouts/{$id}/publish");

        $this->studentHome()->assertJsonCount(1, 'data.sections.1.items')->assertJsonPath('data.sections.1.items.0.title', 'Da outra');
    }

    public function test_hero_banner_configured_in_builder_overrides_featured(): void
    {
        $chosen = $this->video('Escolhido');
        $this->video('Em destaque', ['is_featured' => true]);
        $id = $this->draft();

        $this->postJson("/api/v1/admin/home/layouts/{$id}/banners", ['material_id' => $chosen->id, 'cta_label' => 'Ver agora'])->assertCreated();
        $this->postJson("/api/v1/admin/home/layouts/{$id}/publish");

        $this->studentHome()->assertJsonPath('data.hero.0.title', 'Escolhido')->assertJsonPath('data.hero.0.cta_label', 'Ver agora');
    }

    public function test_banner_period_and_required_fields(): void
    {
        $video = $this->video('Aula');
        $id = $this->draft();

        $this->postJson("/api/v1/admin/home/layouts/{$id}/banners", [])->assertStatus(422)->assertJsonValidationErrors('title');

        $banner = $this->postJson("/api/v1/admin/home/layouts/{$id}/banners", ['material_id' => $video->id, 'starts_at' => now()->addDay()->toIso8601String()])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/home/layouts/{$id}/publish");

        $this->studentHome()->assertJsonCount(0, 'data.hero');
        $this->assertDatabaseHas('banners', ['id' => $banner]);
    }

    public function test_preview_shows_draft_for_chosen_group_without_publishing(): void
    {
        $this->video('Aula');
        $group = StudentGroup::create(['name' => 'Turma', 'slug' => 'turma']);
        $restricted = $this->video('Da turma', ['visibility' => 'restricted', 'group_ids' => [$group->id]]);
        $id = $this->draft();
        $section = $this->postJson("/api/v1/admin/home/layouts/{$id}/sections", $this->section(['title' => 'Só da turma', 'group_ids' => [$group->id]]))->json('data.id');

        $this->getJson("/api/v1/admin/home/layouts/{$id}/preview")->assertOk()->assertJsonCount(1, 'data.sections');

        $preview = $this->getJson("/api/v1/admin/home/layouts/{$id}/preview?group={$group->id}")->assertOk()->json('data');

        $this->assertSame(['Atual', 'Só da turma'], array_column($preview['sections'], 'title'));
        $this->assertContains($restricted->title, array_column($preview['sections'][1]['items'], 'title'));
        $this->assertSame('draft', HomeLayout::find($id)->status);
        $this->assertDatabaseHas('home_sections', ['id' => $section]);
    }

    public function test_published_layout_cannot_be_edited(): void
    {
        $section = $this->published->sections()->first();

        $this->putJson("/api/v1/admin/home/sections/{$section->id}", $this->section())->assertStatus(409)->assertJsonPath('code', 'layout_not_draft');
        $this->deleteJson("/api/v1/admin/home/sections/{$section->id}")->assertStatus(409);
        $this->postJson("/api/v1/admin/home/layouts/{$this->published->id}/sections", $this->section())->assertStatus(409);
        $this->postJson("/api/v1/admin/home/layouts/{$this->published->id}/publish")->assertStatus(409);
    }

    public function test_previous_version_can_be_restored_as_draft(): void
    {
        $id = $this->draft();
        HomeLayout::find($id)->sections()->delete();
        $this->postJson("/api/v1/admin/home/layouts/{$id}/publish");

        $restored = $this->postJson("/api/v1/admin/home/layouts/{$this->published->id}/restore")->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.sections.0.title', 'Atual')
            ->json('data.id');

        $this->assertSame('archived', $this->published->fresh()->status);
        $this->assertSame('published', HomeLayout::find($id)->status);
        $this->assertSame('draft', HomeLayout::find($restored)->status);
    }

    public function test_draft_can_be_discarded(): void
    {
        $id = $this->draft();

        $this->deleteJson("/api/v1/admin/home/layouts/{$id}")->assertNoContent();
        $this->deleteJson("/api/v1/admin/home/layouts/{$this->published->id}")->assertStatus(409);

        $this->assertDatabaseMissing('home_layouts', ['id' => $id]);
    }

    public function test_permission_is_required(): void
    {
        $teacher = User::factory()->create();
        $teacher->roles()->attach(Role::where('slug', Role::TEACHER)->first());
        $this->app['auth']->forgetGuards();

        $this->actingAs($teacher, 'web')->postJson('/api/v1/admin/home/draft', ['surface' => 'home'])->assertForbidden();
    }
}
