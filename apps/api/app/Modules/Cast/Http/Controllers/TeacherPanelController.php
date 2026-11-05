<?php

namespace App\Modules\Cast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Http\Requests\UpdateOwnProfileRequest;
use App\Modules\Cast\Http\Resources\TeacherAdminResource;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Cast\Services\CastService;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Http\Requests\SaveMaterialRequest;
use App\Modules\Materials\Http\Resources\MaterialResource;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialService;
use App\Modules\Materials\Services\MaterialWorkflow;
use App\Support\Http\ApiException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Tudo aqui é limitado ao professor logado e às matérias liberadas pelo administrador. */
class TeacherPanelController extends Controller
{
    private const RELATIONS = ['teacher', 'cover', 'banner', 'primaryFile.upload', 'files.upload', 'categories', 'tags', 'collaborators', 'audienceTargets'];

    public function __construct(
        private MaterialService $materials,
        private MaterialWorkflow $workflow,
        private CastService $cast,
    ) {
    }

    public function overview(Request $request): JsonResponse
    {
        $teacher = $this->teacher($request->user());
        $ids = $this->own($teacher)->pluck('materials.id');

        $views = DB::table('watch_history')->whereIn('material_id', $ids);

        $top = Material::query()
            ->whereIn('id', $ids)
            ->withCount('views')
            ->orderByDesc('views_count')
            ->limit(5)
            ->get(['id', 'title', 'status']);

        return response()->json(['data' => [
            'can_publish' => $teacher->publishesDirectly(),
            'totals' => [
                'published' => $this->own($teacher)->where('status', MaterialStatus::Published)->count(),
                'pending_review' => $this->own($teacher)->where('status', MaterialStatus::PendingReview)->count(),
                'drafts' => $this->own($teacher)->where('status', MaterialStatus::Draft)->count(),
                'views' => (clone $views)->count(),
                'students' => (clone $views)->distinct('student_id')->count('student_id'),
            ],
            'top_materials' => $top->map(fn (Material $material) => [
                'id' => $material->id,
                'title' => $material->title,
                'status' => $material->status->value,
                'views' => $material->views_count,
            ]),
        ]]);
    }

    public function materials(Request $request): AnonymousResourceCollection
    {
        $teacher = $this->teacher($request->user());
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'string', 'max:20']]);

        return MaterialResource::collection(
            $this->own($teacher)
                ->with(self::RELATIONS)
                ->withCount('views')
                ->when($filters['q'] ?? null, fn (Builder $query, $term) => $query->where('title', 'like', "%{$term}%"))
                ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
        );
    }

    public function showMaterial(Material $material): MaterialResource
    {
        Gate::authorize('update', $material);

        return new MaterialResource($material->load(self::RELATIONS));
    }

    public function storeMaterial(SaveMaterialRequest $request): JsonResponse
    {
        Gate::authorize('materials.create');

        $material = $this->materials->save(null, $request->validated(), $request->user());

        return (new MaterialResource($material->refresh()->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function updateMaterial(SaveMaterialRequest $request, Material $material): MaterialResource
    {
        Gate::authorize('update', $material);

        return new MaterialResource($this->materials->save($material, $request->validated(), $request->user())->refresh()->load(self::RELATIONS));
    }

    public function destroyMaterial(Request $request, Material $material): Response
    {
        Gate::authorize('delete', $material);

        $this->materials->delete($material, $request->user());

        return response()->noContent();
    }

    public function releaseMaterial(Request $request, Material $material): MaterialResource
    {
        Gate::authorize('update', $material);

        $this->workflow->release($material, $request->user());

        return new MaterialResource($material->refresh()->load(self::RELATIONS));
    }

    public function categories(Request $request): JsonResponse
    {
        $teacher = $this->teacher($request->user());

        return response()->json(['data' => $teacher->categories()
            ->wherePivot('can_publish', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with('course')
            ->get(['categories.id', 'categories.name', 'categories.parent_id', 'categories.course_id'])
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->course ? "{$category->name} · {$category->course->name}" : $category->name,
                'parent_id' => null,
            ])]);
    }

    public function teachers(Request $request): JsonResponse
    {
        return response()->json(['data' => Teacher::where('status', 'active')
            ->whereKeyNot($this->teacher($request->user())->id)
            ->orderBy('display_name')
            ->get(['id', 'display_name as name'])]);
    }

    public function groups(): JsonResponse
    {
        return response()->json(['data' => StudentGroup::where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function profile(Request $request): TeacherAdminResource
    {
        return new TeacherAdminResource($this->teacher($request->user())->load('user', 'photo', 'banner', 'categories', 'socialLinks'));
    }

    public function updateProfile(UpdateOwnProfileRequest $request): TeacherAdminResource
    {
        $teacher = $this->cast->updateOwnProfile($this->teacher($request->user()), $request->validated(), $request->user());

        return new TeacherAdminResource($teacher->refresh()->load('user', 'photo', 'banner', 'categories', 'socialLinks'));
    }

    private function teacher(User $user): Teacher
    {
        return $user->teacher ?? throw new ApiException('Esta conta não tem perfil de professor.', 'teacher_only', 403);
    }

    private function own(Teacher $teacher): Builder
    {
        return Material::query()->where(fn (Builder $query) => $query
            ->where('teacher_id', $teacher->id)
            ->orWhereHas('collaborators', fn (Builder $collaborators) => $collaborators->whereKey($teacher->id)));
    }
}
