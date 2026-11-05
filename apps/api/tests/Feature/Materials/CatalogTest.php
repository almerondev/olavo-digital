<?php

namespace Tests\Feature\Materials;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialService;
use App\Modules\Materials\Services\MaterialWorkflow;
use App\Modules\Uploads\Models\Upload;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;
    private Teacher $teacher;
    private Category $history;

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
        $this->history = Category::where('slug', 'historia')->first();
        $this->actingAs($this->student, 'web');
    }

    private function material(string $title, array $overrides = [], bool $publish = true): Material
    {
        $key = 'materials/'.Str::uuid().'.pdf';
        Storage::disk('uploads_private')->put($key, '%PDF-1.7');
        $upload = Upload::create([
            'uuid' => (string) Str::uuid(), 'disk' => 'uploads_private', 'object_key' => $key, 'purpose' => 'material',
            'file_class' => 'pdf', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'status' => 'ready',
        ]);
        $material = app(MaterialService::class)->save(null, $overrides + [
            'title' => $title, 'type' => 'pdf', 'visibility' => 'all', 'teacher_id' => $this->teacher->id,
            'file_upload_id' => $upload->id, 'category_ids' => [$this->history->id],
        ], $this->admin);

        return $publish ? app(MaterialWorkflow::class)->publish($material, $this->admin) : $material;
    }

    public function test_catalog_lists_only_available_materials(): void
    {
        $this->material('Público');
        $this->material('Interno', ['visibility' => 'staff']);
        $this->material('Rascunho', [], false);

        $this->getJson('/api/v1/materials')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Público');
    }

    public function test_filters_and_search(): void
    {
        $math = Category::where('slug', 'matematica')->first();
        $other = Teacher::create(['slug' => 'outro', 'display_name' => 'Outro']);
        $this->material('Revolução Industrial', ['tags' => ['Inglaterra']]);
        $this->material('Funções', ['category_ids' => [$math->id], 'teacher_id' => $other->id]);

        $this->getJson("/api/v1/materials?category={$math->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Funções');
        $this->getJson("/api/v1/materials?teacher={$other->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/materials?q=industrial')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/materials?q=inglaterra')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/materials?type=audio')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/materials?from=2099-01-01')->assertJsonCount(0, 'data');
    }

    public function test_relevance_puts_title_match_first(): void
    {
        $this->material('Outro assunto', ['description' => 'fala de genética']);
        $this->material('Genética básica');

        $this->getJson('/api/v1/materials?q=genética&sort=relevance')->assertJsonPath('data.0.title', 'Genética básica');
    }

    public function test_filters_only_offer_what_student_can_see(): void
    {
        $this->material('Público');

        $data = $this->getJson('/api/v1/catalog/filters')->assertOk()->json('data');

        $this->assertSame(['História'], array_column($data['categories'], 'name'));
        $this->assertSame(['Prof'], array_column($data['teachers'], 'name'));
    }

    public function test_detail_exposes_content_data_and_hides_unavailable_material(): void
    {
        $open = $this->material('Aberto', ['tags' => ['Século XVIII']]);
        $hidden = $this->material('Interno', ['visibility' => 'staff']);

        $this->getJson("/api/v1/materials/{$open->slug}")->assertOk()
            ->assertJsonPath('data.teacher', 'Prof')
            ->assertJsonPath('data.category', 'História')
            ->assertJsonPath('data.tags.0', 'Século XVIII')
            ->assertJsonPath('data.file.class', 'pdf');

        $this->getJson("/api/v1/materials/{$hidden->slug}")->assertNotFound();
        $this->getJson("/api/v1/materials/{$hidden->slug}/file")->assertNotFound();
        $this->getJson("/api/v1/materials/{$hidden->slug}/related")->assertNotFound();
    }

    public function test_related_excludes_itself_and_unavailable_items(): void
    {
        $material = $this->material('A');
        $this->material('B');
        $this->material('Interno', ['visibility' => 'staff']);

        $this->getJson("/api/v1/materials/{$material->slug}/related")
            ->assertJsonCount(1, 'data.related')
            ->assertJsonPath('data.related.0.title', 'B')
            ->assertJsonCount(1, 'data.from_teacher');
    }

    public function test_file_returns_temporary_url_and_download_needs_permission(): void
    {
        $locked = $this->material('Sem download');
        $open = $this->material('Com download', ['allow_download' => true]);

        $url = $this->getJson("/api/v1/materials/{$locked->slug}/file")->assertOk()->json('data.url');
        $this->get($url)->assertOk();

        $this->getJson("/api/v1/materials/{$locked->slug}/file?download=1")->assertForbidden()->assertJsonPath('code', 'download_not_allowed');
        $this->getJson("/api/v1/materials/{$open->slug}/file?download=1")->assertOk();
    }

    public function test_link_material_has_no_file(): void
    {
        $link = $this->material('Site', ['type' => 'link', 'external_url' => 'https://www.gov.br/inep', 'file_upload_id' => null]);

        $this->getJson("/api/v1/materials/{$link->slug}/file")->assertNotFound();
        $this->getJson("/api/v1/materials/{$link->slug}")->assertJsonPath('data.external_url', 'https://www.gov.br/inep');
    }
}
