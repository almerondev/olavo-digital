<?php

namespace App\Modules\Playback\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['student_id', 'material_id', 'position_seconds', 'duration_seconds', 'completed_at', 'last_watched_at'])]
class WatchProgress extends Model
{
    protected $table = 'watch_progress';

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'last_watched_at' => 'datetime'];
    }
}
