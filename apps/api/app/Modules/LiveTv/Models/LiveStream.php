<?php

namespace App\Modules\LiveTv\Models;

use App\Modules\Materials\Models\Material;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['live_schedule_id', 'started_at', 'ended_at', 'material_id'])]
class LiveStream extends Model
{
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(LiveSchedule::class, 'live_schedule_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
