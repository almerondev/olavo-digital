<?php

namespace App\Modules\Home\Models;

use App\Modules\Access\Concerns\HasAudience;
use App\Modules\Materials\Models\Material;
use App\Modules\Uploads\Models\Upload;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['home_layout_id', 'placement', 'title', 'description', 'cta_label', 'image_upload_id', 'material_id', 'external_url', 'sort_order', 'is_active', 'starts_at', 'ends_at'])]
class Banner extends Model
{
    use HasAudience;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'image_upload_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function isOnAir(): bool
    {
        return $this->is_active
            && (! $this->starts_at || $this->starts_at->isPast())
            && (! $this->ends_at || $this->ends_at->isFuture());
    }
}
