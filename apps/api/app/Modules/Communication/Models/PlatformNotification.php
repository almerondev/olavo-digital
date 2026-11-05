<?php

namespace App\Modules\Communication\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Uploads\Models\Upload;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('notifications')]
#[Fillable(['type', 'title', 'body', 'url', 'image_upload_id', 'sender_id', 'audience', 'subject_type', 'subject_id', 'recipients_count'])]
class PlatformNotification extends Model
{
    protected function casts(): array
    {
        return ['audience' => 'array'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'image_upload_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class, 'notification_id');
    }
}
