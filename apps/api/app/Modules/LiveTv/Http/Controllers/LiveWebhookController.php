<?php

namespace App\Modules\LiveTv\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\LiveTv\Providers\LiveProvider;
use App\Modules\LiveTv\Services\LiveStreamService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Sem sessão: a autenticidade vem da assinatura do provedor. */
class LiveWebhookController extends Controller
{
    public function __invoke(Request $request, LiveProvider $provider, LiveStreamService $streams, string $name): Response
    {
        abort_unless($name === $provider->name(), 404);

        $streams->handle($provider->parseWebhook($request));

        return response()->noContent();
    }
}
