<?php

namespace App\Modules\Playback\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Materials\Http\Resources\MaterialCardResource;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialAccessService;
use App\Modules\Playback\Services\StudentLibrary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    public function __construct(private StudentLibrary $library, private MaterialAccessService $access)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $student = $this->library->requireStudent($request->user());
        $category = $request->integer('category');

        $favorites = Material::visibleTo($request->user())
            ->with('teacher', 'cover', 'banner', 'categories')
            ->join('favorites', 'favorites.material_id', '=', 'materials.id')
            ->where('favorites.student_id', $student->id)
            ->when($category, fn (Builder $query) => $query->whereHas('categories', fn (Builder $categories) => $categories->where('categories.id', $category)))
            ->orderByDesc('favorites.created_at')
            ->select('materials.*');

        return MaterialCardResource::collection($favorites->paginate(48));
    }

    public function store(Request $request, string $slug): Response
    {
        $student = $this->library->requireStudent($request->user());
        $material = Material::where('slug', $slug)->firstOrFail();

        abort_unless($this->access->canView($request->user(), $material), 404);

        DB::table('favorites')->insertOrIgnore(['student_id' => $student->id, 'material_id' => $material->id, 'created_at' => now()]);

        return response()->noContent();
    }

    public function destroy(Request $request, string $slug): Response
    {
        $student = $this->library->requireStudent($request->user());

        DB::table('favorites')
            ->where('student_id', $student->id)
            ->where('material_id', Material::where('slug', $slug)->value('id'))
            ->delete();

        return response()->noContent();
    }
}
