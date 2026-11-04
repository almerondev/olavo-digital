<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Permission::query()->orderBy('module')->orderBy('slug')->get(['slug', 'module', 'description']),
        ]);
    }
}
