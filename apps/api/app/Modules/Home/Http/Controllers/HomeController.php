<?php

namespace App\Modules\Home\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Home\Models\HomeLayout;
use App\Modules\Home\Models\HomeSection;
use App\Modules\Home\Services\HomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HomeController extends Controller
{
    public function __construct(private HomeService $home)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $surface = $request->validate(['surface' => ['nullable', Rule::in(HomeLayout::SURFACES)]])['surface'] ?? HomeLayout::SURFACE_HOME;

        return response()->json(['data' => $this->home->forUser($request->user(), $surface)]);
    }

    public function items(Request $request, HomeSection $section): JsonResponse
    {
        $page = $this->home->sectionPage($section, $request->user(), max(1, (int) $request->query('page', 1)));

        abort_if($page === null, 404);

        return response()->json(['data' => $page]);
    }
}
