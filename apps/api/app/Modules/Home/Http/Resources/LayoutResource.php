<?php

namespace App\Modules\Home\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'surface' => $this->surface,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
            'banners' => BannerResource::collection($this->whenLoaded('banners')),
        ];
    }
}
