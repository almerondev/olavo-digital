<?php

namespace App\Modules\Cast\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['teacher_id', 'network', 'url', 'sort_order'])]
class TeacherSocialLink extends Model
{
    public const NETWORKS = ['instagram', 'youtube', 'tiktok', 'linkedin', 'site'];
}
