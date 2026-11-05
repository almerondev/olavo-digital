<?php

namespace App\Modules\Cast\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->display_name,
            'short_bio' => $this->short_bio,
            'photo_urls' => (object) ($this->photo?->imageUrls() ?? []),
            'subjects' => $this->categories->pluck('name')->unique()->values(),
        ];
    }
}
