<?php

namespace Database\Seeders;

use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Services\PermissionCatalog;
use App\Modules\Access\Services\PermissionResolver;
use Illuminate\Database\Seeder;

/** Idempotente: pode rodar a cada deploy para registrar permissões novas. */
class AccessSeeder extends Seeder
{
    private const ROLE_NAMES = ['admin' => 'Administrador', 'teacher' => 'Professor', 'student' => 'Aluno'];

    public function run(): void
    {
        foreach (PermissionCatalog::all() as $slug => $permission) {
            Permission::updateOrCreate(['slug' => $slug], $permission);
        }

        foreach (PermissionCatalog::defaultRoles() as $slug => $permissions) {
            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['name' => self::ROLE_NAMES[$slug], 'is_system' => true],
            );

            // Papéis editáveis mantêm os ajustes feitos no painel; admin sempre recebe tudo.
            if ($slug === Role::ADMIN || $role->wasRecentlyCreated) {
                $role->permissions()->sync(Permission::whereIn('slug', $permissions)->pluck('id'));
            }
        }

        app(PermissionResolver::class)->flush();
    }
}
