<?php

namespace App\Modules\Communication\Listeners;

use App\Modules\Communication\Services\NotificationCenter;
use App\Modules\LiveTv\Events\LiveStreamStarted;
use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Events\MaterialPublished;
use App\Modules\Settings\Services\SettingsService;

class NotifyAboutContent
{
    public function __construct(private NotificationCenter $center, private SettingsService $settings)
    {
    }

    public function materialPublished(MaterialPublished $event): void
    {
        if (! $this->settings->get('notifications.auto_material', true)) {
            return;
        }

        $material = $event->material;
        $video = in_array($material->type->value, MaterialType::audiovisual(), true);

        $this->center->sendOnce(
            $material,
            $video ? 'video' : 'material',
            $video ? 'Novo vídeo' : 'Novo material',
            $material->title,
            "/materiais/{$material->slug}",
            ['mode' => 'material', 'ids' => [$material->id]],
            $material->cover_upload_id,
        );
    }

    public function liveStarted(LiveStreamStarted $event): void
    {
        $schedule = $event->stream->schedule;

        if ($schedule->notify_on_start && $this->settings->get('notifications.auto_live', true)) {
            $this->center->sendOnce($event->stream, 'live', 'Estamos ao vivo', $schedule->title, '/ao-vivo', ['mode' => 'live', 'ids' => [$event->stream->id]]);
        }
    }
}
