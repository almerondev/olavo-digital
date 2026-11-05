<?php

namespace App\Modules\Home\Sources;

use App\Modules\Auth\Models\User;
use App\Modules\Home\Models\HomeSection;
use Illuminate\Database\Eloquent\Builder;

/** Uma nova origem de seção é uma nova classe registrada em SourceRegistry; a tela não muda. */
interface SectionSource
{
    public function query(HomeSection $section, User $user): Builder;
}
