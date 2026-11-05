<?php

namespace App\Modules\Home\Models;

use App\Modules\Access\Concerns\HasAudience;
use App\Modules\Materials\Models\Material;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['home_layout_id', 'title', 'description', 'source_type', 'source_config', 'category_id', 'layout', 'max_items', 'sort_order', 'is_active', 'starts_at', 'ends_at'])]
class HomeSection extends Model
{
    use HasAudience;

    public const LAYOUTS = ['carousel', 'carousel_large', 'grid'];

    protected function casts(): array
    {
        return [
            'source_config' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function layoutRecord(): BelongsTo
    {
        return $this->belongsTo(HomeLayout::class, 'home_layout_id');
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'home_section_items')->withPivot('sort_order');
    }

    public function isOnAir(): bool
    {
        return $this->is_active
            && (! $this->starts_at || $this->starts_at->isPast())
            && (! $this->ends_at || $this->ends_at->isFuture());
    }
}
