<?php

namespace Tests\Feature\Auth;

use App\Modules\Auth\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_updates_own_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')
            ->patchJson('/api/v1/me', ['name' => 'Novo Nome', 'phone' => '11999990000'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo Nome');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'phone' => '11999990000']);
    }

    public function test_changing_password_requires_current_one_and_ends_other_sessions(): void
    {
        $user = User::factory()->create(['password' => 'senha-antiga-1']);
        DB::table('sessions')->insert(['id' => 'outra', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($user, 'web')->putJson('/api/v1/me/password', [
            'current_password' => 'incorreta',
            'password' => 'senha-nova-2',
            'password_confirmation' => 'senha-nova-2',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'senha-antiga-1',
            'password' => 'senha-nova-2',
            'password_confirmation' => 'senha-nova-2',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('senha-nova-2', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'outra']);
    }

    public function test_user_cannot_revoke_a_session_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'alheia', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($user, 'web')->deleteJson('/api/v1/me/sessions/alheia')->assertNotFound();

        $this->assertDatabaseHas('sessions', ['id' => 'alheia']);
    }

    public function test_password_reset_does_not_reveal_accounts_and_link_targets_spa(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ninguem@olavo.test'])->assertOk();
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        $this->assertSame($unknown->json('message'), $known->json('message'));

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;
            $this->assertStringStartsWith(config('app.frontend_url').'/redefinir-senha', $url);

            $this->postJson('/api/v1/auth/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'senha-nova-2',
                'password_confirmation' => 'senha-nova-2',
            ])->assertOk();

            return true;
        });

        $this->assertTrue(Hash::check('senha-nova-2', $user->fresh()->password));
    }
}
