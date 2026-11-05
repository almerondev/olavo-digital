<?php

namespace App\Modules\Materials\Http\Resources;

use Illuminate\Http\Request;

class MaterialDetailResource extends MaterialCardResource
{
    public function toArray(Request $request): array
    {
        $viewable = $this->primaryFile?->upload;

        return parent::toArray($request) + [
            'teacher_slug' => $this->teacher?->slug,
            'categories' => $this->categories->pluck('name'),
            'tags' => $this->tags->pluck('name'),
            'published_at' => $this->published_at?->toIso8601String(),
            'external_url' => $this->external_url,
            'video_provider' => $this->videoAsset?->provider,
            'allow_download' => $this->allow_download,
            'file' => $viewable ? [
                'name' => $viewable->original_name,
                'class' => $viewable->file_class,
                'size_bytes' => $viewable->size_bytes,
            ] : null,
        ];
    }
}
