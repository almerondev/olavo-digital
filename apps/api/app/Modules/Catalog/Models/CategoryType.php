<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'is_system', 'sort_order'])]
class CategoryType extends Model
{
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
