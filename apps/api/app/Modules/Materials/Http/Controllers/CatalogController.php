<?php

namespace App\Modules\Materials\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Http\Resources\MaterialCardResource;
use App\Modules\Materials\Http\Resources\MaterialDetailResource;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Models\MaterialFile;
use App\Modules\Materials\Services\MaterialAccessService;
use App\Modules\Uploads\Services\UploadStorage;
use App\Support\Http\ApiException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/** Catálogo do aluno. Toda leitura passa por visibleTo ou MaterialAccessService. */
class CatalogController extends Controller
{
    private const CARD_RELATIONS = ['teacher', 'cover', 'banner', 'categories'];
    private const RELATED_LIMIT = 12;

    public function __construct(private MaterialAccessService $access)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer'],
            'teacher' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'max:30'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', 'in:recent,relevance'],
        ]);

        $query = Material::visibleTo($request->user())
            ->with(self::CARD_RELATIONS)
            ->when($filters['category'] ?? null, fn (Builder $q, $id) => $q->whereHas(
                'categories',
                fn (Builder $categories) => $categories->where('categories.id', $id)->orWhere('categories.parent_id', $id),
            ))
            ->when($filters['teacher'] ?? null, fn (Builder $q, $id) => $q->where('teacher_id', $id))
            ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type))
            ->when($filters['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('published_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $q, $date) => $q->whereDate('published_at', '<=', $date))
            ->when($filters['q'] ?? null, fn (Builder $q, $term) => $q->where(fn (Builder $match) => $match
                ->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', "%{$term}%"))));

        // Relevância provisória até a pesquisa global: título que contém o termo, depois destaques.
        if (($filters['sort'] ?? 'recent') === 'relevance') {
            $query->when($filters['q'] ?? null, fn (Builder $q, $term) => $q->orderByRaw('title like ? desc', ["%{$term}%"]))
                ->orderByDesc('is_featured');
        }

        return MaterialCardResource::collection($query->orderByDesc('published_at')->paginate(24)->withQueryString());
    }

    public function filters(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'categories' => Category::visibleTo($user)
                ->whereNull('parent_id')
                ->whereHas('materials', fn (Builder $materials) => $materials->visibleTo($user))
                ->orderBy('sort_order')
                ->get(['id', 'name']),
            'teachers' => Teacher::whereHas('materials', fn (Builder $materials) => $materials->visibleTo($user))
                ->orderBy('display_name')
                ->get(['id', 'display_name as name']),
        ]]);
    }

    public function show(Request $request, string $slug): MaterialDetailResource
    {
        $material = $this->findVisible($request, $slug);

        return new MaterialDetailResource($material->load([...self::CARD_RELATIONS, 'tags', 'videoAsset', 'primaryFile.upload']));
    }

    public function related(Request $request, string $slug): JsonResponse
    {
        $material = $this->findVisible($request, $slug)->load('categories');
        $user = $request->user();
        $cards = fn (Builder $query) => MaterialCardResource::collection(
            $query->with(self::CARD_RELATIONS)->orderByDesc('published_at')->limit(self::RELATED_LIMIT)->get()
        )->resolve();

        return response()->json(['data' => [
            'related' => $cards(Material::visibleTo($user)
                ->whereKeyNot($material->id)
                ->whereHas('categories', fn (Builder $categories) => $categories->whereIn('categories.id', $material->categories->modelKeys()))),
            'from_teacher' => $material->teacher_id ? $cards(Material::visibleTo($user)
                ->whereKeyNot($material->id)
                ->where('teacher_id', $material->teacher_id)) : [],
        ]]);
    }

    public function file(Request $request, string $slug, UploadStorage $storage): JsonResponse
    {
        $material = $this->findVisible($request, $slug);
        $upload = MaterialFile::where('material_id', $material->id)->where('role', MaterialFile::ROLE_PRIMARY)->first()?->upload;

        if (! $upload?->isReady()) {
            throw new ApiException('Este conteúdo não tem arquivo disponível.', 'file_unavailable', 404);
        }

        if ($request->boolean('download') && ! $material->allow_download) {
            throw new ApiException('O download deste material não está liberado.', 'download_not_allowed', 403);
        }

        return response()->json(['data' => [
            'url' => $storage->temporaryUrl($upload),
            'name' => Str::limit($upload->original_name, 200, ''),
            'mime_type' => $upload->mime_type,
        ]]);
    }

    /** Responde 404 também para material sem acesso, sem revelar que ele existe. */
    private function findVisible(Request $request, string $slug): Material
    {
        $material = Material::where('slug', $slug)->firstOrFail();

        abort_unless($this->access->canView($request->user(), $material), 404);

        return $material;
    }
}
