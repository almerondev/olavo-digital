<?php

namespace Tests\Feature\Access;

use App\Modules\Access\Models\Permission;
use App\Modules\Access\Models\Role;
use App\Modules\Access\Services\PermissionCatalog;
use App\Modules\Access\Services\PermissionResolver;
use App\Modules\Auth\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessSeeder::class);
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $slug)->first());

        return $user;
    }

    public function test_admin_receives_every_permission_of_the_catalog(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(PermissionCatalog::all()),
            app(PermissionResolver::class)->for($this->userWithRole(Role::ADMIN)),
        );
    }

    public function test_student_and_teacher_are_refused_by_the_admin_api(): void
    {
        foreach ([Role::STUDENT, Role::TEACHER] as $role) {
            $this->actingAs($this->userWithRole($role), 'web')
                ->getJson('/api/v1/admin/roles')
                ->assertForbidden()
                ->assertJsonPath('code', 'forbidden');
        }
    }

    public function test_deny_override_removes_a_role_permission(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $permission = Permission::where('slug', 'roles.manage')->first();
        DB::table('user_permissions')->insert(['user_id' => $admin->id, 'permission_id' => $permission->id, 'effect' => 'deny']);
        app(PermissionResolver::class)->flush();

        $this->actingAs($admin, 'web')->getJson('/api/v1/admin/roles')->assertForbidden();
    }

    public function test_allow_override_grants_a_single_permission(): void
    {
        $student = $this->userWithRole(Role::STUDENT);
        $permission = Permission::where('slug', 'audit.view')->first();
        DB::table('user_permissions')->insert(['user_id' => $student->id, 'permission_id' => $permission->id, 'effect' => 'allow']);
        app(PermissionResolver::class)->flush();

        $this->assertTrue(app(PermissionResolver::class)->allows($student, 'audit.view'));
    }

    public function test_admin_creates_a_role_and_change_applies_immediately(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $id = $this->actingAs($admin, 'web')->postJson('/api/v1/admin/roles', [
            'name' => 'Monitor de Redação',
            'permissions' => ['content.view'],
        ])->assertCreated()->assertJsonPath('data.slug', 'monitor-de-redacao')->json('data.id');

        $monitor = User::factory()->create();
        $monitor->roles()->attach($id);
        $resolver = app(PermissionResolver::class);
        $this->assertFalse($resolver->allows($monitor, 'panel.admin'));

        $this->putJson("/api/v1/admin/roles/{$id}", [
            'name' => 'Monitor de Redação',
            'permissions' => ['content.view', 'panel.admin'],
        ])->assertOk();

        $this->assertTrue($resolver->allows($monitor, 'panel.admin'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'role.updated', 'actor_id' => $admin->id]);
    }

    public function test_unknown_permission_is_rejected(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMIN), 'web')->postJson('/api/v1/admin/roles', [
            'name' => 'Inválido',
            'permissions' => ['nao.existe'],
        ])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    }

    public function test_admin_role_cannot_be_edited_and_system_roles_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $adminRole = Role::where('slug', Role::ADMIN)->first();
        $studentRole = Role::where('slug', Role::STUDENT)->first();

        $this->actingAs($admin, 'web')
            ->putJson("/api/v1/admin/roles/{$adminRole->id}", ['name' => 'X', 'permissions' => []])
            ->assertForbidden();

        $this->deleteJson("/api/v1/admin/roles/{$studentRole->id}")->assertForbidden();
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $role = Role::create(['slug' => 'temporario', 'name' => 'Temporário']);
        User::factory()->create()->roles()->attach($role);

        $this->actingAs($admin, 'web')->deleteJson("/api/v1/admin/roles/{$role->id}")->assertForbidden();
    }
}
