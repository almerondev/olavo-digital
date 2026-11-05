<?php

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'token_hash', 'token', 'platform'])]
#[Hidden(['token'])]
class PushDevice extends Model
{
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
