<?php

namespace App\Modules\LiveTv\Services;

use App\Modules\Auth\Models\User;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\Settings\Services\SettingsService;

/** Responde o que está no ar para cada aluno, respeitando curso e público da programação. */
class LiveDirectory
{
    public const SCHEDULE_SETTING = 'live.schedule_enabled';

    private const RELATIONS = ['schedule.teacher.banner', 'schedule.category', 'schedule.course', 'schedule.cover', 'schedule.audienceTargets'];

    public function __construct(private SettingsService $settings)
    {
    }

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
        if ($user->can('live.manage')) {
            return true;
        }

        if ($schedule->course_id && $user->student && ! $user->audienceCourseIds()->contains($schedule->course_id)) {
            return false;
        }

        return $schedule->loadMissing('audienceTargets')->reaches($user->student?->id, $user->audienceGroupIds());
    }

    public function scheduleEnabled(): bool
    {
        return (bool) $this->settings->get(self::SCHEDULE_SETTING, true);
    }
}
