<?php

namespace App\Modules\Communication\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Communication\Jobs\FanOutNotification;
use App\Modules\Communication\Models\PlatformNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NotificationCenter
{
    /** @param array{mode: string, ids?: list<int>} $audience */
    public function send(string $type, string $title, string $body, ?string $url, array $audience, ?User $sender = null, ?int $imageUploadId = null, ?Model $subject = null): PlatformNotification
    {
        $notification = PlatformNotification::create([
            'type' => $type,
            'title' => Str::limit($title, 157),
            'body' => Str::limit($body, 497),
            'url' => $url,
            'image_upload_id' => $imageUploadId,
            'sender_id' => $sender?->id,
            'audience' => $audience,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ]);

        FanOutNotification::dispatch($notification->id);

        return $notification;
    }

    /** Conteúdo avisa uma vez só: republicar ou reabrir não notifica de novo. */
    public function sendOnce(Model $subject, string $type, string $title, string $body, ?string $url, array $audience, ?int $imageUploadId = null): void
    {
        if (PlatformNotification::where(['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey()])->exists()) {
            return;
        }

        $this->send($type, $title, $body, $url, $audience, null, $imageUploadId, $subject);
    }
}
