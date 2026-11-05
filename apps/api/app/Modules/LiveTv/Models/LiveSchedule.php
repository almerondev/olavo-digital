<?php

namespace App\Modules\LiveTv\Models;

use App\Modules\Access\Concerns\HasAudience;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Course;
use App\Modules\LiveTv\Enums\ScheduleStatus;
use App\Modules\Uploads\Models\Upload;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'youtube_url', 'teacher_id', 'category_id', 'course_id', 'cover_upload_id', 'starts_at', 'ends_at', 'status', 'is_adhoc', 'notify_on_start'])]
class LiveSchedule extends Model
{
    use HasAudience;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => ScheduleStatus::class,
            'is_adhoc' => 'boolean',
            'notify_on_start' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'cover_upload_id');
    }

    public function streams(): HasMany
    {
        return $this->hasMany(LiveStream::class);
    }
}
