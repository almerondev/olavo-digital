<?php

namespace App\Modules\Materials\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Materials\Http\Requests\SaveMaterialRequest;
use App\Modules\Materials\Http\Resources\MaterialResource;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialBulkService;
use App\Modules\Materials\Services\MaterialService;
use App\Modules\Materials\Services\MaterialWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MaterialController extends Controller
{
    private const LIST_RELATIONS = ['teacher', 'cover', 'banner', 'primaryFile.upload', 'files.upload', 'categories', 'tags'];

    public function __construct(private MaterialService $materials, private MaterialWorkflow $workflow)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'visibility' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Material::query()->with(self::LIST_RELATIONS)
            ->when($filters['q'] ?? null, fn (Builder $q, $term) => $q->where('title', 'like', "%{$term}%"))
            ->when($filters['teacher_id'] ?? null, fn (Builder $q, $id) => $q->where('teacher_id', $id))
            ->when($filters['type'] ?? null, fn (Builder $q, $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['visibility'] ?? null, fn (Builder $q, $value) => $q->where('visibility', $value))
            ->when($filters['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $id) => $q->whereHas(
                'categories',
                fn (Builder $categories) => $categories->where('categories.id', $id)->orWhere('categories.parent_id', $id),
            ));

        return MaterialResource::collection($query->latest('id')->paginate(25)->withQueryString());
    }

    public function show(Material $material): MaterialResource
    {
        return new MaterialResource($this->loaded($material));
    }

    public function store(SaveMaterialRequest $request): JsonResponse
    {
        $material = $this->materials->save(null, $request->validated(), $request->user());

        return (new MaterialResource($this->loaded($material)))->response()->setStatusCode(201);
    }

    public function update(SaveMaterialRequest $request, Material $material): MaterialResource
    {
        Gate::authorize('update', $material);

        return new MaterialResource($this->loaded($this->materials->save($material, $request->validated(), $request->user())));
    }

    public function destroy(Request $request, Material $material): Response
    {
        Gate::authorize('delete', $material);

        $this->materials->delete($material, $request->user());

        return response()->noContent();
    }

    public function duplicate(Request $request, Material $material): JsonResponse
    {
        Gate::authorize('update', $material);

        $material->load('files', 'categories', 'tags', 'collaborators', 'audienceTargets');
        $copy = $this->materials->duplicate($material, $request->user());

        return (new MaterialResource($this->loaded($copy)))->response()->setStatusCode(201);
    }

    public function transition(Request $request, Material $material, string $action): MaterialResource
    {
        Gate::authorize('update', $material);
        $actor = $request->user();

        match ($action) {
            'release' => $this->workflow->release($material, $actor),
            'approve' => $this->approve($request, $material),
            'reject' => $this->reject($request, $material),
            'unschedule' => $this->workflow->unschedule($material, $actor),
            'archive' => $this->workflow->archive($material, $actor),
            'restore' => $this->workflow->restore($material, $actor),
        };

        return new MaterialResource($this->loaded($material));
    }

    public function bulk(Request $request, MaterialBulkService $bulk): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['publish', 'archive', 'delete', 'set_teacher', 'add_category'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'teacher_id' => ['required_if:action,set_teacher', 'nullable', 'integer', 'exists:teachers,id'],
            'category_id' => ['required_if:action,add_category', 'nullable', 'integer', 'exists:categories,id'],
        ], [], ['action' => 'ação', 'ids' => 'materiais', 'teacher_id' => 'professor', 'category_id' => 'categoria']);

        return response()->json(['data' => $bulk->run($data['action'], $data['ids'], $data, $request->user())]);
    }

    private function approve(Request $request, Material $material): void
    {
        Gate::authorize('review', $material);
        $material->update(['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        $material->publish_at?->isFuture()
            ? $this->workflow->schedule($material, $request->user())
            : $this->workflow->publish($material, $request->user());
    }

    private function reject(Request $request, Material $material): void
    {
        Gate::authorize('review', $material);
        $note = $request->validate(['note' => ['required', 'string', 'max:500']], [], ['note' => 'motivo'])['note'];

        $this->workflow->reject($material, $request->user(), $note);
    }

    private function loaded(Material $material): Material
    {
        return $material->refresh()->load([...self::LIST_RELATIONS, 'collaborators', 'audienceTargets']);
    }
}
