<?php

namespace App\Modules\Communication\Contracts;

use App\Modules\Communication\Models\PlatformNotification;

/** Ponto de entrada do push: o envio real depende das chaves VAPID e de um serviço escolhido na produção. */
interface PushSender
{
    /** @param list<int> $userIds */
    public function send(PlatformNotification $notification, array $userIds): void;
}
