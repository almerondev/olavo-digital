<?php

namespace Tests\Feature\Catalog;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\Student;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\Home\Models\HomeLayout;
use App\Modules\Home\Services\HomeService;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialService;
use App\Modules\Materials\Services\MaterialWorkflow;
use App\Modules\Uploads\Models\Upload;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\CourseSeeder;
use Database\Seeders\HomeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoursesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $enemStudent;
    private User $esaStudent;
    private Teacher $teacher;
    private Category $enemMath;
    private Category $esaMath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, CatalogSeeder::class, CourseSeeder::class, HomeSeeder::class]);
        $this->admin = $this->user(Role::ADMIN);
        $this->enemStudent = $this->enrolled('preparatorio-enem');
        $this->esaStudent = $this->enrolled('preparatorio-esa');
        $this->teacher = Teacher::create(['slug' => 'prof', 'display_name' => 'Prof']);
        $this->enemMath = Category::where('slug', 'matematica')->first();
        $this->esaMath = Category::where('slug', 'esa-matematica')->first();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $role)->first());

        return $user;
    }

    private function enrolled(string ...$courses): User
    {
        $user = $this->user(Role::STUDENT);
        Student::create(['user_id' => $user->id])->courses()->attach(Course::whereIn('slug', $courses)->pluck('id'));

        return $user->fresh();
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    private function published(string $title, array $categories, string $type = 'video'): Material
    {
        $upload = Upload::create([
            'uuid' => (string) Str::uuid(), 'disk' => 'uploads_private', 'object_key' => 'x', 'purpose' => 'material',
            'file_class' => $type, 'original_name' => 'x', 'status' => 'ready',
        ]);
        $material = app(MaterialService::class)->save(null, [
            'title' => $title, 'type' => $type, 'visibility' => 'all', 'teacher_id' => $this->teacher->id,
            'file_upload_id' => $upload->id, 'category_ids' => $categories,
        ], $this->admin);

        return app(MaterialWorkflow::class)->publish($material, $this->admin);
    }

    public function test_seed_creates_the_three_courses_with_own_subjects(): void
    {
        $this->assertSame(['Preparatório ENEM', 'Preparatório ESA', 'Preparatório IFFar'], Course::orderBy('sort_order')->pluck('name')->all());
        $this->assertSame(9, $this->enemMath->course->categories()->count());
        $this->assertSame(5, $this->esaMath->course->categories()->count());
        $this->assertSame(2, Course::where('slug', 'preparatorio-iffar')->first()->categories()->count());
    }

    public function test_student_sees_only_material_of_own_course(): void
    {
        $enem = $this->published('Funções ENEM', [$this->enemMath->id]);
        $esa = $this->published('Conjuntos ESA', [$this->esaMath->id]);

        $this->as($this->enemStudent)->getJson('/api/v1/materials')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Funções ENEM');
        $this->getJson("/api/v1/materials/{$esa->slug}")->assertNotFound();
        $this->getJson("/api/v1/materials/{$esa->slug}/file")->assertNotFound();

        $this->as($this->esaStudent)->getJson('/api/v1/materials')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Conjuntos ESA');
        $this->getJson("/api/v1/materials/{$enem->slug}")->assertNotFound();
    }

    public function test_material_can_be_shared_between_courses(): void
    {
        $this->published('Compartilhado', [$this->enemMath->id, $this->esaMath->id]);

        $this->as($this->enemStudent)->getJson('/api/v1/materials')->assertJsonCount(1, 'data');
        $this->as($this->esaStudent)->getJson('/api/v1/materials')->assertJsonCount(1, 'data');
    }

    public function test_general_content_reaches_every_course(): void
    {
        $general = Category::create(['name' => 'Produções', 'slug' => 'producoes', 'category_type_id' => $this->enemMath->category_type_id]);
        $this->published('Bastidores', [$general->id]);

        $this->as($this->esaStudent)->getJson('/api/v1/materials')->assertJsonCount(1, 'data');
    }

    public function test_student_in_two_courses_sees_both(): void
    {
        $both = $this->enrolled('preparatorio-enem', 'preparatorio-esa');
        $this->published('Um', [$this->enemMath->id]);
        $this->published('Dois', [$this->esaMath->id]);

        $this->as($both)->getJson('/api/v1/materials')->assertJsonCount(2, 'data');
    }

    public function test_student_without_course_sees_only_general_content(): void
    {
        $student = $this->user(Role::STUDENT);
        Student::create(['user_id' => $student->id]);
        $this->published('ENEM', [$this->enemMath->id]);

        $this->as($student->fresh())->getJson('/api/v1/materials')->assertJsonCount(0, 'data');
    }

    public function test_home_lists_only_subjects_of_the_course(): void
    {
        $this->published('Funções ENEM', [$this->enemMath->id]);
        $this->published('Conjuntos ESA', [$this->esaMath->id]);
        app(HomeService::class)->flush();

        $sections = $this->as($this->esaStudent)->getJson('/api/v1/home')->assertOk()->json('data.sections');
        $ids = array_column($sections, 'id');
        $enemSection = HomeLayout::where('surface', 'home')->first()->sections()->where('category_id', $this->enemMath->id)->value('id');

        $this->assertNotContains($enemSection, $ids);
        $recent = collect($sections)->firstWhere('title', 'Novidades')['id'];
        $this->assertSame(['Conjuntos ESA'], array_column($this->getJson("/api/v1/home/sections/{$recent}/items")->json('data.items'), 'title'));
    }

    public function test_filters_show_only_subjects_of_the_course(): void
    {
        $this->published('Funções ENEM', [$this->enemMath->id]);
        $this->published('Conjuntos ESA', [$this->esaMath->id]);

        $data = $this->as($this->esaStudent)->getJson('/api/v1/catalog/filters')->json('data');

        $this->assertSame([$this->esaMath->id], array_column($data['categories'], 'id'));
    }

    public function test_preview_can_simulate_a_course(): void
    {
        $this->published('Conjuntos ESA', [$this->esaMath->id]);
        $layout = HomeLayout::where('surface', 'home')->first();
        $course = $this->esaMath->course_id;

        $titles = fn (array $data) => collect($data['sections'])->pluck('items')->flatten(1)->pluck('title')->unique()->values()->all();

        $this->as($this->admin);
        $this->assertSame([], $titles($this->getJson("/api/v1/admin/home/layouts/{$layout->id}/preview")->json('data')));
        $this->assertSame(['Conjuntos ESA'], $titles($this->getJson("/api/v1/admin/home/layouts/{$layout->id}/preview?course={$course}")->json('data')));
    }

    public function test_teacher_can_receive_subjects_of_more_than_one_course(): void
    {
        $user = $this->user(Role::TEACHER);
        $this->teacher->update(['user_id' => $user->id]);

        $this->as($this->admin)->putJson("/api/v1/admin/teachers/{$this->teacher->id}", ['category_ids' => [$this->enemMath->id, $this->esaMath->id]])->assertOk();

        $this->as($user->fresh())->getJson('/api/v1/teacher/categories')->assertJsonCount(2, 'data');
    }

    public function test_subcategory_inherits_course_of_parent(): void
    {
        $id = $this->as($this->admin)->postJson('/api/v1/admin/categories', [
            'name' => 'Geometria', 'category_type_id' => $this->esaMath->category_type_id, 'parent_id' => $this->esaMath->id, 'visibility' => 'public',
        ])->assertCreated()->json('data.id');

        $this->assertSame($this->esaMath->course_id, Category::find($id)->course_id);
    }

    public function test_admin_manages_courses_and_used_course_is_protected(): void
    {
        $this->as($this->admin);

        $id = $this->postJson('/api/v1/admin/courses', ['name' => 'Preparatório Colégio Militar'])->assertCreated()
            ->assertJsonPath('data.slug', 'preparatorio-colegio-militar')->json('data.id');

        $this->putJson("/api/v1/admin/courses/{$id}", ['name' => 'Colégio Militar', 'is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/v1/admin/courses/{$this->esaMath->course_id}")->assertStatus(409)->assertJsonPath('code', 'course_in_use');
        $this->deleteJson("/api/v1/admin/courses/{$id}")->assertNoContent();
    }
}
