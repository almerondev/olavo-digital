<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\CategoryType;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CategoryTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => CategoryType::orderBy('sort_order')->orderBy('name')->get(['id', 'slug', 'name', 'is_system'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:100']], [], ['name' => 'nome'])['name'];
        $slug = Str::slug($name) ?: 'tipo';

        if (CategoryType::where('slug', $slug)->exists()) {
            throw new ApiException('Já existe um tipo com este nome.', 'category_type_exists', 409);
        }

        $type = CategoryType::create(['name' => $name, 'slug' => $slug, 'sort_order' => (int) CategoryType::max('sort_order') + 1]);

        return response()->json(['data' => $type->only(['id', 'slug', 'name', 'is_system'])], 201);
    }

    public function update(Request $request, CategoryType $categoryType): JsonResponse
    {
        $categoryType->update($request->validate(['name' => ['required', 'string', 'max:100']], [], ['name' => 'nome']));

        return response()->json(['data' => $categoryType->only(['id', 'slug', 'name', 'is_system'])]);
    }

    public function destroy(CategoryType $categoryType): Response
    {
        if ($categoryType->is_system) {
            throw new ApiException('Tipos do sistema não podem ser excluídos.', 'category_type_system', 409);
        }

        if ($categoryType->categories()->withTrashed()->exists()) {
            throw new ApiException('Este tipo está em uso por categorias.', 'category_type_in_use', 409);
        }

        $categoryType->delete();

        return response()->noContent();
    }
}
