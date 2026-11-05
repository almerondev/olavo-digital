<?php

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'endpoint_hash', 'endpoint', 'public_key', 'auth_token', 'user_agent'])]
class PushSubscription extends Model
{
}
