<?php

namespace App\Modules\Access\Services;

use App\Modules\Auth\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionResolver
{
    private const VERSION_KEY = 'permissions:version';

    /** @return list<string> */
    public function for(User $user): array
    {
        $version = Cache::rememberForever(self::VERSION_KEY, fn () => 1);

        return Cache::remember(
            "permissions:{$version}:{$user->id}",
            now()->addMinutes(10),
            fn () => $this->resolve($user),
        );
    }

    public function allows(User $user, string $permission): bool
    {
        return in_array($permission, $this->for($user), true);
    }

    /** Chamado sempre que papéis ou exceções mudam: invalida o cache de todos de uma vez. */
    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 1) + 1);
    }

    /** @return list<string> */
    private function resolve(User $user): array
    {
        $fromRoles = DB::table('user_roles')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('user_roles.user_id', $user->id)
            ->pluck('permissions.slug');

        $overrides = DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->where('user_permissions.user_id', $user->id)
            ->pluck('user_permissions.effect', 'permissions.slug');

        $allowed = $fromRoles
            ->merge($overrides->filter(fn (string $effect) => $effect === 'allow')->keys())
            ->unique()
            ->reject(fn (string $slug) => $overrides->get($slug) === 'deny');

        return $allowed->sort()->values()->all();
    }
}
