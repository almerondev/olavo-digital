<?php

namespace App\Modules\Home\Sources;

use App\Modules\Auth\Models\User;
use App\Modules\Home\Models\HomeSection;
use App\Modules\Materials\Models\Material;
use Illuminate\Database\Eloquent\Builder;

class TeacherSource implements SectionSource
{
    public function query(HomeSection $section, User $user): Builder
    {
        return Material::visibleTo($user)
            ->where('teacher_id', $section->source_config['teacher_id'] ?? 0)
            ->orderByDesc('published_at');
    }
}
