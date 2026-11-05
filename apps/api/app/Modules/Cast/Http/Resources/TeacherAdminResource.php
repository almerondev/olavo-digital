<?php

namespace App\Modules\Cast\Http\Resources;

use App\Modules\Uploads\Http\Resources\UploadResource;
use Illuminate\Http\Request;

class TeacherAdminResource extends TeacherProfileResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'email' => $this->user?->email,
            'status' => $this->status,
            'publish_mode' => $this->publish_mode,
            'show_in_cast' => $this->show_in_cast,
            'photo' => $this->photo ? new UploadResource($this->photo) : null,
            'banner' => $this->banner ? new UploadResource($this->banner) : null,
            'category_ids' => $this->categories->modelKeys(),
            'materials_count' => $this->whenCounted('materials'),
        ];
    }
}
