<?php

namespace App\Modules\Materials\Models;

use App\Modules\Uploads\Models\Upload;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['material_id', 'upload_id', 'role', 'locale', 'sort_order'])]
class MaterialFile extends Model
{
    public const ROLE_PRIMARY = 'primary';
    public const ROLE_SUBTITLE = 'subtitle';

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
