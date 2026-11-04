<?php

namespace App\Modules\Materials\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Models\StudentGroup;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Materials\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Listas enxutas para os seletores dos formulários do painel. */
class OptionsController extends Controller
{
    public function teachers(): JsonResponse
    {
        return response()->json(['data' => Teacher::orderBy('display_name')->get(['id', 'display_name as name'])]);
    }

    public function groups(): JsonResponse
    {
        return response()->json(['data' => StudentGroup::where('is_active', true)->orderBy('name')->get(['id', 'name', 'kind'])]);
    }

    public function tags(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        return response()->json(['data' => Tag::where('name', 'like', "{$term}%")->orderBy('name')->limit(10)->pluck('name')]);
    }
}
