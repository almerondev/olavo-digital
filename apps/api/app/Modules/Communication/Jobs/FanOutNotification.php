<?php

namespace App\Modules\Communication\Jobs;

use App\Modules\Communication\Contracts\PushSender;
use App\Modules\Communication\Models\NotificationRecipient;
use App\Modules\Communication\Models\PlatformNotification;
use App\Modules\Communication\Services\NotificationAudience;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** A chave única de destinatário permite repetir o job sem duplicar nada. */
class FanOutNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(public int $notificationId)
    {
    }

    public function handle(NotificationAudience $audience, PushSender $push): void
    {
        $notification = PlatformNotification::find($this->notificationId);

        if (! $notification) {
            return;
        }

        $audience->eachChunk($notification->audience, function (array $userIds) use ($notification, $push) {
            NotificationRecipient::insertOrIgnore(array_map(fn (int $userId) => [
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'created_at' => $notification->created_at,
            ], $userIds));

            $push->send($notification, $userIds);
        });

        $notification->update(['recipients_count' => $notification->recipients()->count()]);
    }
}
