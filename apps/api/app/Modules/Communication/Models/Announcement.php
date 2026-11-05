<?php

namespace App\Modules\Communication\Models;

use App\Modules\Access\Concerns\HasAudience;
use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Course;
use App\Modules\Uploads\Models\Upload;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'body', 'image_upload_id', 'display_mode', 'priority', 'course_id', 'published_at', 'expires_at', 'is_pinned', 'requires_ack', 'send_notification', 'notified_at', 'created_by'])]
class Announcement extends Model
{
    use HasAudience, SoftDeletes;

    public const MODES = ['banner', 'card', 'section'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'notified_at' => 'datetime',
            'is_pinned' => 'boolean',
            'requires_ack' => 'boolean',
            'send_notification' => 'boolean',
        ];
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'image_upload_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function scopeOnAir(Builder $query): Builder
    {
        return $query->where('published_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** Aluno vê o que é geral ou do seu curso, e só se estiver no público. Equipe vê tudo que está no ar. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->onAir()->when($user->student, fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->whereNull('course_id')->orWhereIn('course_id', $user->audienceCourseIds()))
            ->where(fn (Builder $query) => $query->whereDoesntHave('audienceTargets')->orWhere(fn (Builder $query) => $query->targeting($user))));
    }
}
