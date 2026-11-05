<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Uploads\Http\Resources\UploadResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category_type_id' => $this->category_type_id,
            'parent_id' => $this->parent_id,
            'course_id' => $this->course_id,
            'course_name' => $this->course?->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'cover' => $this->cover ? new UploadResource($this->cover) : null,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'visibility' => $this->visibility,
            'materials_count' => $this->whenCounted('materials'),
            'teacher_ids' => $this->whenLoaded('teachers', fn () => $this->teachers->modelKeys()),
            'group_ids' => $this->whenLoaded('audienceTargets', fn () => $this->audienceTargets->pluck('student_group_id')->filter()->values()),
        ];
    }
}
