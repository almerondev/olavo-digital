<?php

namespace App\Modules\People\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Models\StudentGroup;
use App\Support\Audit\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class GroupAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => StudentGroup::withCount('students')->orderByDesc('is_active')->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $group = StudentGroup::create([...$data, 'slug' => $this->uniqueSlug($data['name'])]);
        Audit::record('group.created', $group);

        return response()->json(['data' => $group->loadCount('students')], 201);
    }

    public function update(Request $request, StudentGroup $group): JsonResponse
    {
        $group->update($this->validated($request));
        Audit::record('group.updated', $group);

        return response()->json(['data' => $group->loadCount('students')]);
    }

    public function destroy(StudentGroup $group): Response
    {
        $group->delete();
        Audit::record('group.deleted', $group);

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'in:turma,grupo'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'is_active' => ['required', 'boolean'],
        ], [], ['name' => 'nome']);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 100, '');
        $slug = $base;

        for ($suffix = 2; StudentGroup::where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
