<?php

namespace App\Modules\Home\Sources;

use App\Modules\Auth\Models\User;
use App\Modules\Home\Models\HomeSection;
use App\Modules\Materials\Models\Material;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Regra simples, por categorias do histórico. A recomendação inteligente pode trocar só esta classe. */
class RecommendedSource implements SectionSource
{
    private const TOP_CATEGORIES = 3;

    public function query(HomeSection $section, User $user): Builder
    {
        $studentId = $user->student?->id ?? 0;

        $categories = DB::table('watch_history')
            ->join('material_categories', 'material_categories.material_id', '=', 'watch_history.material_id')
            ->where('watch_history.student_id', $studentId)
            ->groupBy('material_categories.category_id')
            ->orderByRaw('count(*) desc')
            ->limit(self::TOP_CATEGORIES)
            ->pluck('material_categories.category_id');

        return Material::visibleTo($user)
            ->whereHas('categories', fn (Builder $query) => $query->whereIn('categories.id', $categories))
            ->whereNotIn('materials.id', DB::table('watch_history')->where('student_id', $studentId)->select('material_id'))
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at');
    }
}
