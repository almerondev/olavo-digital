<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\SaveCategoryRequest;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use App\Support\Audit\Audit;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->with('cover', 'course', 'teachers', 'audienceTargets')->withCount('materials')->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function store(SaveCategoryRequest $request): JsonResponse
    {
        $category = $this->persist(new Category, $request->validated());
        Audit::record('category.created', $category);

        return (new CategoryResource($this->loaded($category)))->response()->setStatusCode(201);
    }

    public function update(SaveCategoryRequest $request, Category $category): CategoryResource
    {
        $this->assertValidParent($category, $request->validated('parent_id'));

        $this->persist($category, $request->validated());
        Audit::record('category.updated', $category);

        return new CategoryResource($this->loaded($category));
    }

    public function destroy(Category $category): Response
    {
        if ($category->children()->exists()) {
            throw new ApiException('Remova ou mova as subcategorias antes de excluir.', 'category_has_children', 409);
        }

        if ($category->materials()->exists()) {
            throw new ApiException('Esta categoria tem materiais. Desative-a ou mova os materiais antes.', 'category_in_use', 409);
        }

        $category->delete();
        Audit::record('category.deleted', $category);

        return response()->noContent();
    }

    public function reorder(Request $request): Response
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:categories,id']]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $position => $id) {
                Category::whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return response()->noContent();
    }

    /** @param array<string, mixed> $data */
    private function persist(Category $category, array $data): Category
    {
        return DB::transaction(function () use ($category, $data) {
            $category->fill(collect($data)->except(['teacher_ids', 'group_ids'])->all());

            // Subcategoria sempre fica no curso da categoria superior.
            if ($category->parent_id) {
                $category->course_id = Category::whereKey($category->parent_id)->value('course_id');
            }

            if (! $category->exists) {
                $category->slug = $this->uniqueSlug($data['name']);
                $category->sort_order = (int) Category::where('parent_id', $data['parent_id'] ?? null)->max('sort_order') + 1;
            }

            $category->save();
            $category->teachers()->sync($data['teacher_ids'] ?? []);
            $category->syncAudience($category->visibility === 'restricted' ? ($data['group_ids'] ?? []) : []);

            return $category;
        });
    }

    private function assertValidParent(Category $category, ?int $parentId): void
    {
        for ($current = $parentId; $current; $current = Category::whereKey($current)->value('parent_id')) {
            if ($current === $category->id) {
                throw ValidationException::withMessages(['parent_id' => 'Uma categoria não pode ficar dentro dela mesma.']);
            }
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;

        for ($i = 2; Category::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    private function loaded(Category $category): Category
    {
        return $category->refresh()->load('cover', 'course', 'teachers', 'audienceTargets')->loadCount('materials');
    }
}
