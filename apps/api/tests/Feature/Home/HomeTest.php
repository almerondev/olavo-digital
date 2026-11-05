<?php

namespace Tests\Feature\Home;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Home\Models\HomeLayout;
use App\Modules\Home\Models\HomeSection;
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

class HomeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private HomeLayout $layout;
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
        $this->layout = HomeLayout::create(['name' => 'Teste', 'status' => HomeLayout::PUBLISHED, 'published_at' => now()]);
        $this->history = Category::where('slug', 'historia')->first();
    }

    private function material(string $title, array $overrides = []): Material
    {
        $upload = Upload::create([
            'uuid' => (string) Str::uuid(), 'disk' => 'uploads_private', 'object_key' => 'x.pdf', 'purpose' => 'material',
            'file_class' => 'video', 'original_name' => 'x.mp4', 'status' => 'ready',
        ]);
        $teacher = Teacher::firstOrCreate(['slug' => 'prof'], ['display_name' => 'Prof']);
        $material = app(MaterialService::class)->save(null, $overrides + [
            'title' => $title, 'type' => 'video', 'visibility' => 'all', 'teacher_id' => $teacher->id,
            'file_upload_id' => $upload->id, 'category_ids' => [$this->history->id],
        ], $this->admin);

        return app(MaterialWorkflow::class)->publish($material, $this->admin);
    }

    private function section(array $attributes): HomeSection
    {
        $section = $this->layout->sections()->create($attributes + ['title' => 'Seção', 'source_type' => 'recent']);
        app(HomeService::class)->flush();

        return $section;
    }

    private function home()
    {
        app(HomeService::class)->flush();

        return $this->actingAs($this->student, 'web')->getJson('/api/v1/home')->assertOk();
    }

    public function test_home_is_built_from_database_in_configured_order(): void
    {
        $this->material('Aula');
        $this->section(['title' => 'Segunda', 'sort_order' => 2]);
        $this->section(['title' => 'Primeira', 'sort_order' => 1]);

        $this->home()
            ->assertJsonPath('data.sections.0.title', 'Primeira')
            ->assertJsonPath('data.sections.1.title', 'Segunda')
            ->assertJsonPath('data.sections.0.items.0.title', 'Aula');
    }

    public function test_inactive_out_of_period_or_empty_sections_are_hidden(): void
    {
        $this->material('Aula');
        $this->section(['title' => 'Inativa', 'is_active' => false]);
        $this->section(['title' => 'Futura', 'starts_at' => now()->addDay()]);
        $this->section(['title' => 'Vencida', 'ends_at' => now()->subDay()]);
        $this->section(['title' => 'Vazia', 'source_type' => 'featured']);
        $this->section(['title' => 'Visível']);

        $this->home()->assertJsonCount(1, 'data.sections')->assertJsonPath('data.sections.0.title', 'Visível');
    }

    public function test_section_audience_limits_who_sees_it(): void
    {
        $this->material('Aula');
        $group = StudentGroup::create(['name' => 'Turma', 'slug' => 'turma']);
        $section = $this->section(['title' => 'Só da turma']);
        $section->syncAudience([$group->id]);

        $this->home()->assertJsonCount(0, 'data.sections');

        $group->students()->attach($this->student->student);

        $this->home()->assertJsonPath('data.sections.0.title', 'Só da turma');
    }

    public function test_category_section_includes_subcategories_and_respects_max_items(): void
    {
        $child = Category::create(['name' => 'Brasil', 'slug' => 'brasil', 'category_type_id' => $this->history->category_type_id, 'parent_id' => $this->history->id]);
        $this->material('Na filha', ['category_ids' => [$child->id]]);
        $this->material('Na mãe');
        $this->section(['source_type' => 'category', 'category_id' => $this->history->id, 'max_items' => 1]);

        $this->home()->assertJsonCount(1, 'data.sections.0.items')->assertJsonPath('data.sections.0.has_more', false);
    }

    public function test_manual_section_keeps_chosen_order(): void
    {
        $first = $this->material('Primeiro');
        $second = $this->material('Segundo');
        $section = $this->section(['source_type' => 'manual']);
        $section->materials()->attach([$second->id => ['sort_order' => 0], $first->id => ['sort_order' => 1]]);

        $this->home()
            ->assertJsonPath('data.sections.0.items.0.title', 'Segundo')
            ->assertJsonPath('data.sections.0.items.1.title', 'Primeiro');
    }

    public function test_sections_beyond_the_first_load_on_demand_with_pagination(): void
    {
        foreach (range(1, 14) as $i) {
            $this->material("Aula {$i}");
        }

        foreach (range(0, 3) as $i) {
            $last = $this->section(['title' => "S{$i}", 'sort_order' => $i]);
        }

        $sections = $this->home()->json('data.sections');
        $this->assertArrayHasKey('items', $sections[2]);
        $this->assertArrayNotHasKey('items', $sections[3]);

        $this->getJson("/api/v1/home/sections/{$last->id}/items")->assertJsonCount(12, 'data.items')->assertJsonPath('data.has_more', true);
        $this->getJson("/api/v1/home/sections/{$last->id}/items?page=2")->assertJsonCount(2, 'data.items')->assertJsonPath('data.has_more', false);
    }

    public function test_section_items_are_refused_for_audience_outside_or_draft_layouts(): void
    {
        $group = StudentGroup::create(['name' => 'Turma', 'slug' => 'turma']);
        $section = $this->section([]);
        $section->syncAudience([$group->id]);
        $draft = HomeLayout::create(['name' => 'Rascunho'])->sections()->create(['title' => 'X', 'source_type' => 'recent']);
        app(HomeService::class)->flush();

        $this->actingAs($this->student, 'web');
        $this->getJson("/api/v1/home/sections/{$section->id}/items")->assertNotFound();
        $this->getJson("/api/v1/home/sections/{$draft->id}/items")->assertNotFound();
    }

    public function test_hero_shows_only_material_available_to_the_student(): void
    {
        $open = $this->material('Aberto', ['description' => 'Texto']);
        $staff = $this->material('Interno', ['visibility' => 'staff']);
        $this->layout->banners()->create(['material_id' => $staff->id, 'sort_order' => 0]);
        $this->layout->banners()->create(['material_id' => $open->id, 'sort_order' => 1]);
        $this->layout->banners()->create(['title' => 'Vencido', 'ends_at' => now()->subDay(), 'sort_order' => 2]);

        $this->home()
            ->assertJsonCount(1, 'data.hero')
            ->assertJsonPath('data.hero.0.title', 'Aberto')
            ->assertJsonPath('data.hero.0.description', 'Texto')
            ->assertJsonPath('data.hero.0.material_slug', $open->slug);
    }

    public function test_card_never_exposes_management_fields(): void
    {
        $this->material('Aula');
        $this->section([]);

        $card = $this->home()->json('data.sections.0.items.0');

        $this->assertArrayNotHasKey('status', $card);
        $this->assertArrayNotHasKey('review_note', $card);
        $this->assertTrue($card['is_new']);
    }

    public function test_home_shows_only_videos_and_materials_page_shows_the_rest(): void
    {
        $this->material('Videoaula');
        $this->material('Apostila', ['type' => 'link', 'external_url' => 'https://www.gov.br/inep', 'file_upload_id' => null]);
        $this->section(['title' => 'Novidades']);
        $materials = HomeLayout::create(['name' => 'Materiais', 'surface' => 'materials', 'status' => HomeLayout::PUBLISHED, 'published_at' => now()]);
        $materials->sections()->create(['title' => 'Novidades', 'source_type' => 'recent']);

        $this->home()
            ->assertJsonCount(1, 'data.sections.0.items')
            ->assertJsonPath('data.sections.0.items.0.title', 'Videoaula');

        $this->getJson('/api/v1/home?surface=materials')
            ->assertJsonCount(1, 'data.sections.0.items')
            ->assertJsonPath('data.sections.0.items.0.title', 'Apostila');

        $this->getJson('/api/v1/home?surface=invalida')->assertStatus(422);
    }

    public function test_hero_falls_back_to_latest_featured_video_with_preview(): void
    {
        $upload = Upload::create([
            'uuid' => (string) Str::uuid(), 'disk' => 'uploads_private', 'object_key' => 'v.mp4', 'purpose' => 'material',
            'file_class' => 'video', 'original_name' => 'v.mp4', 'status' => 'ready',
        ]);
        $this->material('Comum');
        $this->material('Em destaque', ['is_featured' => true, 'file_upload_id' => $upload->id, 'description' => 'Texto']);

        $hero = $this->home()->assertJsonCount(1, 'data.hero')->json('data.hero.0');

        $this->assertSame('Em destaque', $hero['title']);
        $this->assertSame('file', $hero['preview']['kind']);
        $this->assertStringContainsString($upload->uuid, $hero['preview']['url']);
    }

    public function test_featured_material_outside_audience_is_not_used_as_hero(): void
    {
        $group = StudentGroup::create(['name' => 'Turma', 'slug' => 'turma']);
        $this->material('Restrito', ['is_featured' => true, 'visibility' => 'restricted', 'group_ids' => [$group->id]]);

        $this->home()->assertJsonCount(0, 'data.hero');
    }

    public function test_external_video_preview_uses_embed_and_document_has_none(): void
    {
        $this->material('YouTube', ['type' => 'external_video', 'external_url' => 'https://www.youtube.com/watch?v=abc123', 'file_upload_id' => null, 'is_featured' => true]);
        $this->material('Apostila', ['type' => 'link', 'external_url' => 'https://www.gov.br/inep', 'file_upload_id' => null, 'is_featured' => true]);
        $materials = HomeLayout::create(['name' => 'Materiais', 'surface' => 'materials', 'status' => HomeLayout::PUBLISHED, 'published_at' => now()]);

        $this->home()
            ->assertJsonPath('data.hero.0.preview.kind', 'embed')
            ->assertJsonPath('data.hero.0.title', 'YouTube');

        $this->getJson('/api/v1/home?surface=materials')
            ->assertJsonPath('data.hero.0.title', 'Apostila')
            ->assertJsonPath('data.hero.0.preview', null);
    }

    public function test_home_works_on_a_cache_store_that_serializes(): void
    {
        config(['cache.default' => 'database']);
        $material = $this->material('Aula');
        $group = StudentGroup::create(['name' => 'Turma', 'slug' => 'turma']);
        $this->section(['title' => 'Aberta']);
        $this->section(['title' => 'Restrita'])->syncAudience([$group->id]);
        $this->layout->banners()->create(['material_id' => $material->id]);
        app(HomeService::class)->flush();

        $this->actingAs($this->student, 'web');

        foreach ([1, 2] as $request) {
            $this->getJson('/api/v1/home')->assertOk()
                ->assertJsonCount(1, 'data.sections')
                ->assertJsonPath('data.hero.0.title', 'Aula');
        }
    }

    public function test_guest_cannot_read_home(): void
    {
        $this->getJson('/api/v1/home')->assertUnauthorized();
    }
}
