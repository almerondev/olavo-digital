<?php

namespace App\Modules\Home\Sources;

use App\Modules\Auth\Models\User;
use App\Modules\Home\Models\HomeSection;
use App\Modules\Materials\Models\Material;
use Illuminate\Database\Eloquent\Builder;

class ContinueWatchingSource implements SectionSource
{
    public function query(HomeSection $section, User $user): Builder
    {
        return Material::visibleTo($user)
            ->join('watch_progress', 'watch_progress.material_id', '=', 'materials.id')
            ->where('watch_progress.student_id', $user->student?->id ?? 0)
            ->whereNull('watch_progress.completed_at')
            ->where('watch_progress.position_seconds', '>', 0)
            ->orderByDesc('watch_progress.last_watched_at')
            ->select('materials.*');
    }
}
