<?php

namespace App\Modules\Auth\Http\Middleware;

use App\Modules\Auth\Services\SessionManager;
use App\Support\Http\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function __construct(private SessionManager $sessions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $this->sessions->revokeAll($user);

            throw new ApiException('Sua conta está bloqueada. Fale com a secretaria.', 'account_blocked', 403);
        }

        return $next($request);
    }
}
