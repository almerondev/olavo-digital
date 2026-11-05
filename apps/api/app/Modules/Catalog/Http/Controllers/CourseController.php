<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Course;
use App\Support\Audit\Audit;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Course::withCount('categories', 'students')->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Course $course) => $this->present($course))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $course = Course::create($data + [
            'slug' => $this->uniqueSlug($data['name']),
            'sort_order' => (int) Course::max('sort_order') + 1,
        ]);
        Audit::record('course.created', null, ['course_id' => $course->id]);

        return response()->json(['data' => $this->present($course->loadCount('categories', 'students'))], 201);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $course->update($this->validated($request));
        Audit::record('course.updated', null, ['course_id' => $course->id]);

        return response()->json(['data' => $this->present($course->loadCount('categories', 'students'))]);
    }

    public function destroy(Course $course): Response
    {
        if ($course->categories()->exists() || $course->students()->exists()) {
            throw new ApiException('Este curso tem matérias ou alunos. Desative-o em vez de excluir.', 'course_in_use', 409);
        }

        $course->delete();
        Audit::record('course.deleted', null, ['course_id' => $course->id]);

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ], [], ['name' => 'nome', 'description' => 'descrição']);
    }

    /** @return array<string, mixed> */
    private function present(Course $course): array
    {
        return $course->only(['id', 'name', 'slug', 'description', 'is_active', 'categories_count', 'students_count']);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'curso';
        $slug = $base;

        for ($i = 2; Course::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
