<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Http\Requests\SaveRoleRequest;
use App\Modules\Access\Http\Resources\RoleResource;
use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Services\PermissionResolver;
use App\Support\Audit\Audit;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function __construct(private PermissionResolver $resolver)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(
            Role::query()->with('permissions')->withCount('users')->orderBy('name')->get()
        );
    }

    public function store(SaveRoleRequest $request): RoleResource
    {
        $role = DB::transaction(function () use ($request) {
            $role = Role::create([
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'slug' => $this->uniqueSlug($request->validated('name')),
            ]);
            $this->syncPermissions($role, $request->validated('permissions'));

            return $role;
        });

        Audit::record('role.created', $role, ['permissions' => $request->validated('permissions')]);

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function update(SaveRoleRequest $request, Role $role): RoleResource
    {
        Gate::authorize('update', $role);

        DB::transaction(function () use ($request, $role) {
            $role->update($request->safe()->only(['name', 'description']));
            $this->syncPermissions($role, $request->validated('permissions'));
        });

        $this->resolver->flush();
        Audit::record('role.updated', $role, ['permissions' => $request->validated('permissions')]);

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function destroy(Role $role): Response
    {
        Gate::authorize('delete', $role);

        $role->delete();
        Audit::record('role.deleted', null, ['slug' => $role->slug]);

        return response()->noContent();
    }

    /** @param list<string> $slugs */
    private function syncPermissions(Role $role, array $slugs): void
    {
        $role->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id'));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;

        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
