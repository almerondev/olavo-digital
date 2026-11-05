<?php

namespace App\Modules\Cast\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cast\Http\Resources\TeacherCardResource;
use App\Modules\Cast\Http\Resources\TeacherProfileResource;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Http\Resources\MaterialCardResource;
use App\Modules\Materials\Models\Material;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CastController extends Controller
{
    private const CARD_LIMIT = 24;

    public function index(): AnonymousResourceCollection
    {
        return TeacherCardResource::collection(
            Teacher::inCast()->with('photo', 'categories')->orderBy('sort_order')->orderBy('display_name')->get()
        );
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $teacher = Teacher::inCast()->with('photo', 'banner', 'categories', 'socialLinks')->where('slug', $slug)->firstOrFail();
        $user = $request->user();

        $cards = fn (Builder $query) => MaterialCardResource::collection(
            $query->with('teacher', 'cover', 'banner', 'categories')->orderByDesc('published_at')->limit(self::CARD_LIMIT)->get()
        )->resolve();

        $own = fn () => Material::visibleTo($user)->where(fn (Builder $query) => $query
            ->where('teacher_id', $teacher->id)
            ->orWhereHas('collaborators', fn (Builder $collaborators) => $collaborators->whereKey($teacher->id)));

        $special = fn (Builder $categories) => $categories->whereHas('type', fn (Builder $type) => $type->where('slug', 'especial'));

        return response()->json(['data' => [
            'teacher' => new TeacherProfileResource($teacher),
            'videos' => $cards($own()->whereIn('type', MaterialType::audiovisual())->whereDoesntHave('categories', $special)),
            'specials' => $cards($own()->whereHas('categories', $special)),
            'materials' => $cards($own()->whereNotIn('type', MaterialType::audiovisual())),
        ]]);
    }
}
