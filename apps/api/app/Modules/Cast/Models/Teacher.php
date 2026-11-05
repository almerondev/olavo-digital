<?php

namespace App\Modules\Cast\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Materials\Models\Material;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Uploads\Models\Upload;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'slug', 'display_name', 'short_bio', 'bio', 'photo_upload_id', 'banner_upload_id', 'specialties', 'publish_mode', 'show_in_cast', 'status', 'sort_order'])]
class Teacher extends Model
{
    use SoftDeletes;

    public const PUBLISH_SETTING = 'materials.teacher_publish_mode';

    protected function casts(): array
    {
        return ['specialties' => 'array', 'show_in_cast' => 'boolean'];
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'photo_upload_id');
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Upload::class, 'banner_upload_id');
    }

    public function socialLinks(): HasMany
    {
        return $this->hasMany(TeacherSocialLink::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'teacher_categories')->withPivot('can_publish');
    }

    public function scopeInCast(Builder $query): Builder
    {
        return $query->where('show_in_cast', true)->where('status', 'active');
    }

    /** A regra do professor vence a geral; "inherit" segue a configuração da plataforma. */
    public function publishesDirectly(): bool
    {
        $mode = $this->publish_mode === 'inherit'
            ? app(SettingsService::class)->get(self::PUBLISH_SETTING, 'approval')
            : $this->publish_mode;

        return $mode === 'immediate';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
