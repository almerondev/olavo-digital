<?php

namespace App\Modules\Playback\Models;

use App\Modules\Materials\Models\Material;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'material_id', 'started_at', 'last_event_at', 'seconds_watched', 'hidden_at'])]
class WatchHistory extends Model
{
    public $timestamps = false;

    protected $table = 'watch_history';

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'last_event_at' => 'datetime', 'hidden_at' => 'datetime'];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
