<?php

namespace App\Modules\Materials\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'provider_ref', 'status', 'duration_seconds', 'thumbnail_url', 'meta'])]
class VideoAsset extends Model
{
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
