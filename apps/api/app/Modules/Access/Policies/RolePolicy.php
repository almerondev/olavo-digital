<?php

namespace App\Modules\Access\Policies;

use App\Modules\Access\Models\Role;
use App\Modules\Auth\Models\User;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    public function update(User $user, Role $role): Response
    {
        return $role->slug === Role::ADMIN
            ? Response::deny('O papel de administrador sempre tem acesso completo e não pode ser alterado.')
            : Response::allow();
    }

    public function delete(User $user, Role $role): Response
    {
        if ($role->is_system) {
            return Response::deny('Papéis do sistema não podem ser excluídos.');
        }

        return $role->users()->exists()
            ? Response::deny('Remova este papel dos usuários antes de excluí-lo.')
            : Response::allow();
    }
}
