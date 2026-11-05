<?php

namespace App\Modules\LiveTv\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'provider', 'provider_input_id', 'ingest_rtmps_url', 'ingest_srt_url', 'stream_key', 'playback_ref', 'is_active'])]
#[Hidden(['stream_key'])]
class LiveChannel extends Model
{
    protected function casts(): array
    {
        return ['stream_key' => 'encrypted', 'is_active' => 'boolean'];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(LiveSchedule::class);
    }

    public function streams(): HasMany
    {
        return $this->hasMany(LiveStream::class);
    }
}
