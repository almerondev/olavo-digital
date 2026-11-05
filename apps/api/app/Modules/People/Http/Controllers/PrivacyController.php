<?php

namespace App\Modules\People\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\People\Services\PrivacyService;
use App\Modules\Settings\Services\SettingsService;
use App\Support\Audit\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PrivacyController extends Controller
{
    public function acceptTerms(Request $request, SettingsService $settings): Response
    {
        $user = $request->user();
        $user->forceFill(['terms_version' => $settings->get('legal.version'), 'terms_accepted_at' => now()])->save();
        Audit::record('privacy.terms_accepted', $user, ['version' => $user->terms_version]);

        return response()->noContent();
    }

    public function export(Request $request, PrivacyService $privacy): JsonResponse
    {
        Audit::record('privacy.exported', $request->user());

        return response()->json(['data' => $privacy->export($request->user())]);
    }

    public function requestDeletion(Request $request): Response
    {
        $request->user()->forceFill(['deletion_requested_at' => now()])->save();
        Audit::record('privacy.deletion_requested', $request->user());

        return response()->noContent();
    }

    public function cancelDeletion(Request $request): Response
    {
        $request->user()->forceFill(['deletion_requested_at' => null])->save();
        Audit::record('privacy.deletion_cancelled', $request->user());

        return response()->noContent();
    }
}
