<?php

namespace App\Modules\Playback\Http\Resources;

use App\Modules\Materials\Http\Resources\MaterialCardResource;
use App\Modules\Playback\Services\StudentLibrary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistoryEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accessed_at' => $this->last_event_at->toIso8601String(),
            'seconds_watched' => $this->seconds_watched,
            'completed' => (bool) (app(StudentLibrary::class)->progressOf($this->material_id)['completed'] ?? false),
            'material' => (new MaterialCardResource($this->material))->resolve($request),
        ];
    }
}
