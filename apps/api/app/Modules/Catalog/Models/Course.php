<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Access\Models\Student;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Preparatório. Cada um tem as próprias matérias, e o aluno só enxerga os cursos em que está. */
#[Fillable(['name', 'slug', 'description', 'is_active', 'sort_order'])]
class Course extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->withPivot('enrolled_at');
    }
}
