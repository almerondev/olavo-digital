<?php

namespace App\Modules\Materials\Http\Resources;

use App\Modules\Playback\Services\StudentLibrary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Versão enxuta para cards do aluno: sem dados de gestão, como status ou revisão. */
class MaterialCardResource extends JsonResource
{
    private const NEW_FOR_DAYS = 7;

    public function toArray(Request $request): array
    {
        $primary = $this->categories->firstWhere('pivot.is_primary', true) ?? $this->categories->first();
        $library = app(StudentLibrary::class)->forUser($request->user());

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'teacher' => $this->teacher?->display_name,
            'category' => $primary?->name,
            'image_urls' => (object) ($this->cover?->imageUrls() ?? []),
            'banner_urls' => (object) ($this->banner?->imageUrls() ?? []),
            'is_new' => (bool) $this->published_at?->gt(now()->subDays(self::NEW_FOR_DAYS)),
            'is_favorite' => $library->isFavorite($this->id),
            'progress' => $library->progressOf($this->id),
        ];
    }
}
