<?php

namespace App\Modules\Access\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['student_group_id', 'student_id'])]
class AudienceTarget extends Model
{
    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }
}
