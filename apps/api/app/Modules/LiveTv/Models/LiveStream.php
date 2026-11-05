<?php

namespace App\Modules\LiveTv\Models;

use App\Modules\Materials\Models\Material;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['live_schedule_id', 'live_channel_id', 'provider_session_ref', 'started_at', 'disconnected_at', 'ended_at', 'peak_viewers', 'material_id'])]
class LiveStream extends Model
{
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'disconnected_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(LiveSchedule::class, 'live_schedule_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(LiveChannel::class, 'live_channel_id');
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
