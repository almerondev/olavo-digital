<?php

namespace App\Modules\Uploads\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UploadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'original_name' => $this->original_name,
            'file_class' => $this->file_class,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'image_urls' => (object) $this->imageUrls(),
        ];
    }
}
