<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Resources\UserResource;
use App\Modules\Auth\Models\User;
use App\Support\Audit\Audit;
use App\Support\Http\ApiException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function store(LoginRequest $request): UserResource
    {
        $request->ensureIsNotRateLimited();

        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($request->throttleKey(), 300);

            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (! $user->isActive()) {
            Auth::guard('web')->logout();
            Audit::record('auth.login_blocked', $user, actor: $user);

            throw new ApiException('Sua conta está bloqueada. Fale com a secretaria.', 'account_blocked', 403);
        }

        RateLimiter::clear($request->throttleKey());
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        Audit::record('auth.login', $user, actor: $user);

        return new UserResource($user->load('roles'));
    }

    public function destroy(Request $request): Response
    {
        Audit::record('auth.logout');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
