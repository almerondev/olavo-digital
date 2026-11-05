<?php

namespace App\Modules\LiveTv\Enums;

enum ScheduleStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Ended = 'ended';
    case Cancelled = 'cancelled';
}
