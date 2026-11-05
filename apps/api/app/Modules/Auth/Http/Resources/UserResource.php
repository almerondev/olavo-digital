<?php

namespace App\Modules\Auth\Http\Resources;

use App\Modules\Access\Services\PermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'avatar_urls' => (object) ($this->avatar?->imageUrls() ?? []),
            'is_student' => $this->student !== null,
            'preferences' => $this->preferences ?? (object) [],
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'terms_pending' => $this->termsPending(),
            'deletion_requested_at' => $this->deletion_requested_at?->toIso8601String(),
            'roles' => $this->roles->pluck('slug'),
            'permissions' => app(PermissionResolver::class)->for($this->resource),
        ];
    }
}
