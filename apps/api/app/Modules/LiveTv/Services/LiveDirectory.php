<?php

namespace App\Modules\LiveTv\Services;

use App\Modules\Auth\Models\User;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Models\LiveStream;

/** Responde o que está no ar para cada aluno, respeitando o público da programação. */
class LiveDirectory
{
    private const RELATIONS = ['schedule.teacher.banner', 'schedule.category', 'schedule.cover', 'schedule.audienceTargets'];

    public function currentFor(User $user): ?LiveStream
    {
        return LiveStream::open()
            ->with(self::RELATIONS)
            ->latest('started_at')
            ->get()
            ->first(fn (LiveStream $stream) => $this->reaches($stream->schedule, $user));
    }

    public function reaches(LiveSchedule $schedule, User $user): bool
    {
        return $user->can('live.manage')
            || $schedule->loadMissing('audienceTargets')->reaches($user->student?->id, $user->audienceGroupIds());
    }
}
