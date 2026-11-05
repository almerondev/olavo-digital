<?php

namespace App\Modules\Materials\Http\Resources;

use App\Modules\Materials\Models\MaterialFile;
use App\Modules\Uploads\Http\Resources\UploadResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'visibility' => $this->visibility->value,
            'teacher' => $this->teacher ? ['id' => $this->teacher->id, 'name' => $this->teacher->display_name] : null,
            'cover' => $this->cover ? new UploadResource($this->cover) : null,
            'banner' => $this->banner ? new UploadResource($this->banner) : null,
            'file' => $this->primaryFile ? new UploadResource($this->primaryFile->upload) : null,
            'subtitle' => ($subtitle = $this->files->firstWhere('role', MaterialFile::ROLE_SUBTITLE)) ? new UploadResource($subtitle->upload) : null,
            'external_url' => $this->external_url,
            'publish_at' => $this->publish_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'allow_download' => $this->allow_download,
            'is_featured' => $this->is_featured,
            'review_note' => $this->review_note,
            'categories' => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'is_primary' => (bool) $category->pivot->is_primary,
            ]),
            'tags' => $this->tags->pluck('name'),
            'collaborator_ids' => $this->whenLoaded('collaborators', fn () => $this->collaborators->modelKeys()),
            'group_ids' => $this->whenLoaded('audienceTargets', fn () => $this->audienceTargets->pluck('student_group_id')->filter()->values()),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
