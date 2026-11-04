<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Services\SessionManager;
use App\Support\Audit\Audit;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SessionController extends Controller
{
    public function __construct(private SessionManager $sessions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $current = $request->hasSession() ? $request->session()->getId() : null;

        return response()->json([
            'data' => $this->sessions->list($request->user())->map(fn ($session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity_at' => date(DATE_ATOM, $session->last_activity),
                'is_current' => $session->id === $current,
            ]),
        ]);
    }

    public function destroy(Request $request, string $session): Response
    {
        if ($request->hasSession() && $session === $request->session()->getId()) {
            throw new ApiException('Para encerrar esta sessão, use Sair.', 'current_session', 409);
        }

        if (! $this->sessions->revoke($request->user(), $session)) {
            throw new ApiException('Sessão não encontrada.', 'not_found', 404);
        }

        Audit::record('auth.session_revoked', $request->user());

        return response()->noContent();
    }
}
