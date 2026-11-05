<?php

namespace App\Modules\Home\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'surface', 'status', 'published_at', 'published_by'])]
class HomeLayout extends Model
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED = 'archived';

    /** Início reúne vídeos; Materiais reúne PDFs e demais arquivos. */
    public const SURFACE_HOME = 'home';
    public const SURFACE_MATERIALS = 'materials';
    public const SURFACES = [self::SURFACE_HOME, self::SURFACE_MATERIALS];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(HomeSection::class)->orderBy('sort_order');
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class)->orderBy('sort_order');
    }
}
