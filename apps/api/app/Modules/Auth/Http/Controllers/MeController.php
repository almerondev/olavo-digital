<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\UpdatePasswordRequest;
use App\Modules\Auth\Http\Requests\UpdateProfileRequest;
use App\Modules\Auth\Http\Resources\UserResource;
use App\Modules\Auth\Services\SessionManager;
use App\Support\Audit\Audit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user()->load('roles'));
    }

    public function updatePassword(UpdatePasswordRequest $request, SessionManager $sessions): Response
    {
        $user = $request->user();
        $user->update(['password' => $request->validated('password')]);

        $sessions->revokeAll($user, $request->hasSession() ? $request->session()->getId() : null);
        Audit::record('auth.password_changed', $user);

        return response()->noContent();
    }
}
