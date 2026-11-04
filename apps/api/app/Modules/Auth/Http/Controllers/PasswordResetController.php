<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\SessionManager;
use App\Support\Audit\Audit;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function forgot(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']], [], ['email' => 'e-mail']);

        Password::sendResetLink($request->only('email'));

        // Mesma resposta para e-mail existente ou não, para não revelar quem tem conta.
        return response()->json(['message' => 'Se o e-mail estiver cadastrado, enviaremos um link de redefinição.']);
    }

    public function reset(Request $request, SessionManager $sessions): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [], ['email' => 'e-mail', 'password' => 'nova senha']);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($sessions) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $sessions->revokeAll($user);
                Audit::record('auth.password_reset', $user, actor: $user);
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Este link é inválido ou expirou.']);
        }

        return response()->json(['message' => 'Senha redefinida. Entre com a nova senha.']);
    }
}
