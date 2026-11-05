<?php

namespace App\Modules\LiveTv\Events;

use App\Modules\LiveTv\Models\LiveStream;
use Illuminate\Foundation\Events\Dispatchable;

class LiveStreamStarted
{
    use Dispatchable;

    public function __construct(public LiveStream $stream)
    {
    }
}
