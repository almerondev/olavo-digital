<?php

namespace App\Modules\Home\Sources;

use App\Modules\Auth\Models\User;
use App\Modules\Home\Models\HomeSection;
use App\Modules\Materials\Models\Material;
use Illuminate\Database\Eloquent\Builder;

class RecentSource implements SectionSource
{
    public function query(HomeSection $section, User $user): Builder
    {
        return Material::visibleTo($user)->orderByDesc('published_at');
    }
}
