<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Http\JsonResponse;

class PublicConfigController extends Controller
{
    public function __invoke(SettingsService $settings): JsonResponse
    {
        return response()->json(['data' => $settings->public()]);
    }
}
