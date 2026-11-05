<?php

namespace App\Modules\Communication\Contracts;

use App\Modules\Communication\Models\PlatformNotification;

interface PushSender
{
    /** @param list<int> $userIds */
    public function send(PlatformNotification $notification, array $userIds): void;
}
