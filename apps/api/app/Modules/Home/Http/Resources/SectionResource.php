<?php

namespace App\Modules\Home\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'source_type' => $this->source_type,
            'category_id' => $this->category_id,
            'teacher_id' => $this->source_config['teacher_id'] ?? null,
            'layout' => $this->layout,
            'max_items' => $this->max_items,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'materials' => $this->materials->sortBy('pivot.sort_order')->map(fn ($material) => ['id' => $material->id, 'title' => $material->title])->values(),
            'group_ids' => $this->audienceTargets->pluck('student_group_id')->filter()->values(),
        ];
    }
}
