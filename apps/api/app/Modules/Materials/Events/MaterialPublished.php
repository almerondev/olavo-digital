<?php

namespace App\Modules\Materials\Events;

use App\Modules\Materials\Models\Material;
use Illuminate\Foundation\Events\Dispatchable;

class MaterialPublished
{
    use Dispatchable;

    public function __construct(public Material $material)
    {
    }
}
