<?php

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['notification_id', 'user_id', 'read_at', 'created_at'])]
class NotificationRecipient extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(PlatformNotification::class, 'notification_id');
    }
}
