<?php

namespace App\Modules\Uploads\Enums;

enum UploadStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Rejected = 'rejected';
}
