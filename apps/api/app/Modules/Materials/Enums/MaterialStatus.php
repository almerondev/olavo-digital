<?php

namespace App\Modules\Materials\Enums;

enum MaterialStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
