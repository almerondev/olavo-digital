<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'is_public'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'json', 'is_public' => 'boolean'];
    }
}
