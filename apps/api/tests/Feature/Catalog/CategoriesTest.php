<?php

namespace Tests\Feature\Catalog;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CategoryType;
use App\Modules\Materials\Models\Material;
use Database\Seeders\AccessSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $typeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, CatalogSeeder::class]);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', Role::ADMIN)->first());
        $this->typeId = CategoryType::where('slug', 'materia')->value('id');
        $this->actingAs($this->admin, 'web');
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['name' => 'Nova categoria', 'category_type_id' => $this->typeId, 'visibility' => 'public', 'is_active' => true];
    }

    public function test_seed_creates_types_and_subjects_without_duplicates_on_rerun(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertSame(7, CategoryType::count());
        $this->assertSame(9, Category::count());
    }

    public function test_admin_creates_subcategory_with_audience_and_teachers(): void
    {
        $parent = Category::where('slug', 'historia')->first();
        $group = StudentGroup::create(['name' => 'Turma A', 'slug' => 'turma-a']);

        $id = $this->postJson('/api/v1/admin/categories', $this->payload([
            'name' => 'História do Brasil',
            'parent_id' => $parent->id,
            'visibility' => 'restricted',
            'group_ids' => [$group->id],
        ]))->assertCreated()->assertJsonPath('data.slug', 'historia-do-brasil')->json('data.id');

        $this->assertDatabaseHas('audience_targets', ['targetable_type' => 'category', 'targetable_id' => $id, 'student_group_id' => $group->id]);
    }

    public function test_category_cannot_become_child_of_its_own_descendant(): void
    {
        $parent = Category::where('slug', 'historia')->first();
        $child = Category::create(['name' => 'Filha', 'slug' => 'filha', 'category_type_id' => $this->typeId, 'parent_id' => $parent->id]);

        $this->putJson("/api/v1/admin/categories/{$parent->id}", $this->payload(['name' => 'História', 'parent_id' => $child->id]))
            ->assertStatus(422)->assertJsonValidationErrors('parent_id');
    }

    public function test_category_in_use_or_with_children_cannot_be_deleted(): void
    {
        $parent = Category::where('slug', 'historia')->first();
        Category::create(['name' => 'Filha', 'slug' => 'filha', 'category_type_id' => $this->typeId, 'parent_id' => $parent->id]);

        $this->deleteJson("/api/v1/admin/categories/{$parent->id}")->assertStatus(409)->assertJsonPath('code', 'category_has_children');

        $math = Category::where('slug', 'matematica')->first();
        $material = Material::create(['slug' => 'm', 'title' => 'M', 'type' => 'pdf']);
        $material->categories()->attach($math);

        $this->deleteJson("/api/v1/admin/categories/{$math->id}")->assertStatus(409)->assertJsonPath('code', 'category_in_use');

        $free = Category::where('slug', 'fisica')->first();
        $this->deleteJson("/api/v1/admin/categories/{$free->id}")->assertNoContent();
        $this->assertSoftDeleted($free);
    }

    public function test_reorder_persists_positions(): void
    {
        $ids = Category::orderByDesc('sort_order')->pluck('id')->all();

        $this->postJson('/api/v1/admin/categories/reorder', ['ids' => $ids])->assertNoContent();

        $this->assertSame($ids[0], Category::orderBy('sort_order')->value('id'));
    }

    public function test_new_types_can_be_registered_but_system_types_are_protected(): void
    {
        $id = $this->postJson('/api/v1/admin/category-types', ['name' => 'Simulados'])->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/admin/category-types/{$this->typeId}")->assertStatus(409);
        $this->deleteJson("/api/v1/admin/category-types/{$id}")->assertNoContent();
    }

    public function test_teacher_cannot_manage_categories(): void
    {
        $teacher = User::factory()->create();
        $teacher->roles()->attach(Role::where('slug', Role::TEACHER)->first());

        $this->actingAs($teacher, 'web')->postJson('/api/v1/admin/categories', $this->payload())->assertForbidden();
    }
}
