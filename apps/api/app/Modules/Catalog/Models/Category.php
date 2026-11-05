<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Access\Concerns\HasAudience;
use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Materials\Models\Material;
use App\Modules\Uploads\Models\Upload;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['category_type_id', 'parent_id', 'course_id', 'slug', 'name', 'description', 'icon', 'cover_upload_id', 'sort_order', 'is_active', 'visibility'])]
class Category extends Model
{
    use HasAudience, SoftDeletes;

    public const VISIBILITIES = ['public', 'restricted', 'hidden'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CategoryType::class, 'category_type_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'cover_upload_id');
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Teacher::class, 'teacher_categories')->withPivot('can_publish');
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'material_categories')->withPivot('is_primary');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('is_active', true)
            // Aluno só enxerga conteúdo geral e o dos cursos em que está. Equipe vê todos.
            ->when($user->student, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('categories.course_id')
                ->orWhereIn('categories.course_id', $user->audienceCourseIds())))
            ->where(function (Builder $query) use ($user) {
            $query->where('visibility', 'public')
                ->orWhere(fn (Builder $restricted) => $restricted->where('visibility', 'restricted')->targeting($user));
        });
    }
}
