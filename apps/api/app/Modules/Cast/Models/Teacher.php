<?php

namespace App\Modules\Cast\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Materials\Models\Material;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'slug', 'display_name', 'short_bio', 'bio', 'specialties', 'publish_mode', 'show_in_cast', 'status', 'sort_order'])]
class Teacher extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['specialties' => 'array', 'show_in_cast' => 'boolean'];
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
