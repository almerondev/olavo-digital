<?php

namespace App\Modules\Materials\Policies;

use App\Modules\Auth\Models\User;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Models\Material;
use Illuminate\Auth\Access\Response;

class MaterialPolicy
{
    public function update(User $user, Material $material): bool
    {
        return $user->can('materials.manage_all')
            || ($user->can('materials.update') && $material->isManagedBy($user));
    }

    public function delete(User $user, Material $material): Response
    {
        if ($user->can('materials.manage_all')) {
            return Response::allow();
        }

        return $material->teacher_id === $user->teacher?->id && $material->status === MaterialStatus::Draft
            ? Response::allow()
            : Response::deny('Só rascunhos próprios podem ser excluídos.');
    }

    public function publish(User $user, Material $material): bool
    {
        return $user->can('materials.publish') && $this->update($user, $material);
    }

    public function review(User $user, Material $material): bool
    {
        return $user->can('materials.review');
    }
}
