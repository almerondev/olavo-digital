<?php

namespace App\Modules\Access\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'kind', 'year', 'is_active'])]
class StudentGroup extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_group_members')->withPivot('joined_at');
    }
}
