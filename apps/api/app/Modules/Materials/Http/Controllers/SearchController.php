<?php

namespace App\Modules\Materials\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cast\Http\Resources\TeacherCardResource;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Materials\Enums\MaterialType as Type;
use App\Modules\Materials\Http\Resources\MaterialCardResource;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Resultado separado por tipo, como na busca de streaming: cada grupo vira uma fileira. */
class SearchController extends Controller
{
    private const MATERIAL_LIMIT = 120;
    private const TEACHER_LIMIT = 12;

    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'category' => ['nullable', 'integer'],
            'teacher' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:30'],
        ]);

        $user = $request->user();
        $like = MaterialSearch::pattern($filters['q']);

        $materials = MaterialSearch::apply(Material::visibleTo($user), $filters['q'])
            ->with('teacher', 'cover', 'banner', 'categories')
            ->when($filters['category'] ?? null, fn (Builder $q, $id) => $q->whereHas(
                'categories',
                fn (Builder $categories) => $categories->where('categories.id', $id)->orWhere('categories.parent_id', $id),
            ))
            ->when($filters['teacher'] ?? null, fn (Builder $q, $id) => $q->where('teacher_id', $id))
            ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type))
            ->orderByRaw('materials.title like ? desc', [$like])
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->limit(self::MATERIAL_LIMIT)
            ->get();

        $groups = collect($this->groups())
            ->map(fn (array $group, string $key) => [
                'key' => $key,
                'label' => $group['label'],
                'items' => MaterialCardResource::collection(
                    $materials->filter(fn (Material $material) => in_array($material->type, $group['types'], true))->values()
                )->resolve(),
            ])
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values();

        $teachers = Teacher::inCast()
            ->with('photo', 'categories')
            ->where(fn (Builder $query) => $query
                ->where('display_name', 'like', $like)
                ->orWhereHas('categories', fn (Builder $categories) => $categories->where('categories.name', 'like', $like)))
            ->whereHas('materials', fn (Builder $query) => $query->visibleTo($user))
            ->orderBy('display_name')
            ->limit(self::TEACHER_LIMIT)
            ->get();

        return response()->json(['data' => [
            'total' => $materials->count(),
            'groups' => $groups,
            'teachers' => TeacherCardResource::collection($teachers)->resolve(),
        ]]);
    }

    /** @return array<string, array{label: string, types: list<Type>}> */
    private function groups(): array
    {
        return [
            'videos' => ['label' => 'Videoaulas', 'types' => [Type::Video, Type::ExternalVideo]],
            'pdfs' => ['label' => 'PDFs', 'types' => [Type::Pdf]],
            'audios' => ['label' => 'Áudios', 'types' => [Type::Audio]],
            'documents' => ['label' => 'Documentos e apresentações', 'types' => [Type::Document, Type::Presentation, Type::Spreadsheet]],
            'extras' => ['label' => 'Materiais complementares', 'types' => [Type::Image, Type::Link, Type::Other]],
        ];
    }
}
