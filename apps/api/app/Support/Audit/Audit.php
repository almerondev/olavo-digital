<?php

namespace App\Support\Audit;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class Audit
{
    /** @param array<string, mixed>|null $changes */
    public static function record(string $action, ?Model $subject = null, ?array $changes = null, ?User $actor = null): void
    {
        $request = request();

        ActivityLog::create([
            'actor_id' => ($actor ?? $request->user())?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
        ]);
    }
}
