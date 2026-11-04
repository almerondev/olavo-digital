<?php

namespace Tests\Feature\Auth;

use App\Modules\Access\Models\Role;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessSeeder::class);
        $this->withHeader('Origin', 'http://localhost:5173');
    }

    public function test_login_returns_user_with_permissions_and_records_access(): void
    {
        $user = User::factory()->create(['password' => 'senha-valida-1']);
        $user->roles()->attach(Role::where('slug', Role::STUDENT)->first());

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'senha-valida-1'])
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles.0', 'student')
            ->assertJsonPath('data.permissions.0', 'content.view');

        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login', 'actor_id' => $user->id]);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_wrong_password_returns_standard_validation_error(): void
    {
        $user = User::factory()->create(['password' => 'senha-valida-1']);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'errada'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.email.0', 'E-mail ou senha incorretos.');
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'errada'])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'errada'])->assertStatus(429);
    }

    public function test_blocked_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => 'senha-valida-1', 'status' => UserStatus::Blocked]);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'senha-valida-1'])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_blocked');

        $this->assertGuest('web');
    }

    public function test_user_blocked_during_session_loses_access_and_sessions(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $user->update(['status' => UserStatus::Blocked]);

        $this->actingAs($user, 'web')->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('code', 'account_blocked');

        $this->assertDatabaseMissing('sessions', ['id' => 'abc']);
    }

    public function test_guest_receives_standard_401(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create(['password' => 'senha-valida-1']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'senha-valida-1'])->assertOk();

        $this->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertGuest('web');
    }
}
