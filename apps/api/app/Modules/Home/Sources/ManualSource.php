<?php

namespace App\Modules\Home\Sources;

use App\Modules\Auth\Models\User;
use App\Modules\Home\Models\HomeSection;
use App\Modules\Materials\Models\Material;
use Illuminate\Database\Eloquent\Builder;

class ManualSource implements SectionSource
{
    public function query(HomeSection $section, User $user): Builder
    {
        return Material::visibleTo($user)
            ->join('home_section_items', 'home_section_items.material_id', '=', 'materials.id')
            ->where('home_section_items.home_section_id', $section->id)
            ->orderBy('home_section_items.sort_order')
            ->select('materials.*');
    }
}
