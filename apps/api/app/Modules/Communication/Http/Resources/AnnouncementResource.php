<?php

namespace App\Modules\Communication\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'image_urls' => (object) ($this->image?->imageUrls() ?? []),
            'display_mode' => $this->display_mode,
            'priority' => $this->priority,
            'is_pinned' => $this->is_pinned,
            'requires_ack' => $this->requires_ack,
            'read_at' => $this->whenLoaded('reads', fn () => $this->reads->first()?->read_at?->toIso8601String()),
            'published_at' => $this->published_at->toIso8601String(),
        ];
    }
}
