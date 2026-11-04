<?php

namespace App\Providers;

use App\Modules\Access\Models\Role;
use App\Modules\Access\Policies\RolePolicy;
use App\Modules\Access\Services\PermissionCatalog;
use App\Modules\Access\Services\PermissionResolver;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Policies\MaterialPolicy;
use App\Modules\Uploads\Http\Controllers\UploadController;
use App\Modules\Uploads\Services\LocalUploadStorage;
use App\Modules\Uploads\Services\S3UploadStorage;
use App\Modules\Uploads\Services\UploadStorage;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionResolver::class);
        $this->app->bind(UploadStorage::class, fn () => config('olavo.uploads.driver') === 's3'
            ? new S3UploadStorage
            : new LocalUploadStorage);
    }

    public function boot(): void
    {
        Relation::morphMap([
            'user' => User::class,
            'role' => Role::class,
            'category' => Category::class,
            'material' => Material::class,
        ]);

        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Material::class, MaterialPolicy::class);
        Gate::define('uploads-files', fn (User $user) => $user->canAny(UploadController::uploaderPermissions()));

        // Só responde por permissões do catálogo; as demais abilities seguem para as Policies,
        // que aplicam o escopo (dono do material, matérias autorizadas).
        Gate::before(function (User $user, string $ability) {
            return PermissionCatalog::has($ability)
                ? app(PermissionResolver::class)->allows($user, $ability)
                : null;
        });

        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('app.frontend_url')
            .'/redefinir-senha?token='.$token.'&email='.urlencode($user->email));
    }
}
