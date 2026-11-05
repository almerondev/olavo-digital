<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Contracts\PushSender;
use App\Modules\Communication\Models\PlatformNotification;

class NullPushSender implements PushSender
{
    public function send(PlatformNotification $notification, array $userIds): void
    {
    }
}
