<?php

namespace App\Modules\Materials\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Materials\Models\Material;

class MaterialAccessService
{
    public function canView(User $user, Material $material): bool
    {
        return $user->can('materials.manage_all')
            || $material->isManagedBy($user)
            || Material::query()->whereKey($material->id)->visibleTo($user)->exists();
    }
}
