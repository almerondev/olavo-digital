<?php

namespace App\Modules\Home\Sources;

use App\Modules\Auth\Models\User;
use App\Modules\Home\Models\HomeSection;
use App\Modules\Materials\Models\Material;
use Illuminate\Database\Eloquent\Builder;

class CategorySource implements SectionSource
{
    public function query(HomeSection $section, User $user): Builder
    {
        return Material::visibleTo($user)
            ->whereHas('categories', fn (Builder $categories) => $categories
                ->where('categories.id', $section->category_id)
                ->orWhere('categories.parent_id', $section->category_id))
            ->orderByDesc('published_at');
    }
}
