<?php

namespace App\Modules\Cast\Http\Resources;

use Illuminate\Http\Request;

class TeacherProfileResource extends TeacherCardResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'bio' => $this->bio,
            'specialties' => $this->specialties ?? [],
            'banner_urls' => (object) ($this->banner?->imageUrls() ?? []),
            'socials' => $this->socialLinks->sortBy('sort_order')->map(fn ($link) => ['network' => $link->network, 'url' => $link->url])->values(),
        ];
    }
}
