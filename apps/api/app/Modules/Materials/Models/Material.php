<?php

namespace App\Modules\Materials\Models;

use App\Modules\Access\Concerns\HasAudience;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Enums\MaterialVisibility;
use App\Modules\Uploads\Models\Upload;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['slug', 'title', 'description', 'type', 'status', 'visibility', 'teacher_id', 'cover_upload_id', 'video_asset_id', 'external_url', 'publish_at', 'published_at', 'archived_at', 'allow_download', 'is_featured', 'created_by', 'reviewed_by', 'reviewed_at', 'review_note'])]
class Material extends Model
{
    use HasAudience, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => MaterialType::class,
            'status' => MaterialStatus::class,
            'visibility' => MaterialVisibility::class,
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'allow_download' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'cover_upload_id');
    }

    public function videoAsset(): BelongsTo
    {
        return $this->belongsTo(VideoAsset::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(MaterialFile::class);
    }

    public function primaryFile(): HasOne
    {
        return $this->hasOne(MaterialFile::class)->where('role', MaterialFile::ROLE_PRIMARY);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'material_categories')->withPivot('is_primary');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'material_collaborators');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'material_tags');
    }

    /** Publicado, no ar, liberado para o aluno e com ao menos uma categoria acessível a ele. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->where('status', MaterialStatus::Published)
            ->where('published_at', '<=', now())
            ->where(function (Builder $query) use ($user) {
                $query->where('visibility', MaterialVisibility::All)
                    ->orWhere(fn (Builder $restricted) => $restricted
                        ->where('visibility', MaterialVisibility::Restricted)
                        ->targeting($user));
            })
            ->whereHas('categories', fn (Builder $categories) => $categories->visibleTo($user));
    }

    public function isManagedBy(User $user): bool
    {
        $teacherId = $user->teacher?->id;

        if (! $teacherId) {
            return false;
        }

        return $this->teacher_id === $teacherId || $this->collaborators()->whereKey($teacherId)->exists();
    }
}
