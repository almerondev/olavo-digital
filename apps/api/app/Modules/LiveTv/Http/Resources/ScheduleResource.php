<?php

namespace App\Modules\LiveTv\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'teacher' => $this->teacher ? ['id' => $this->teacher->id, 'name' => $this->teacher->display_name, 'slug' => $this->teacher->slug] : null,
            'category' => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null,
            'cover_urls' => (object) ($this->cover?->imageUrls() ?? []),
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'status' => $this->status->value,
            'is_adhoc' => $this->is_adhoc,
            'notify_on_start' => $this->notify_on_start,
            'live_channel_id' => $this->live_channel_id,
            'group_ids' => $this->whenLoaded('audienceTargets', fn () => $this->audienceTargets->pluck('student_group_id')->filter()->values()),
        ];
    }
}
