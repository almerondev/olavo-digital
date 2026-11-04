<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SessionManager
{
    public function list(User $user): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']);
    }

    public function revoke(User $user, string $sessionId): bool
    {
        return DB::table('sessions')->where('user_id', $user->id)->where('id', $sessionId)->delete() > 0;
    }

    public function revokeAll(User $user, ?string $exceptSessionId = null): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
            ->delete();
    }
}
