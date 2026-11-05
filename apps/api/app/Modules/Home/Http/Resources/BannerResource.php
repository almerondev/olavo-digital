<?php

namespace App\Modules\Home\Http\Resources;

use App\Modules\Uploads\Http\Resources\UploadResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'cta_label' => $this->cta_label,
            'image' => $this->image ? new UploadResource($this->image) : null,
            'media_type' => $this->media_type,
            'video' => $this->video ? new UploadResource($this->video) : null,
            'material' => $this->material ? ['id' => $this->material->id, 'title' => $this->material->title] : null,
            'external_url' => $this->external_url,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'group_ids' => $this->audienceTargets->pluck('student_group_id')->filter()->values(),
        ];
    }
}
