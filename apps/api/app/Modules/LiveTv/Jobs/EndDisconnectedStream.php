<?php

namespace App\Modules\LiveTv\Jobs;

use App\Modules\LiveTv\Services\LiveStreamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class EndDisconnectedStream implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $streamId, public string $disconnectedAt)
    {
    }

    public function handle(LiveStreamService $streams): void
    {
        $streams->endIfStillDisconnected($this->streamId, $this->disconnectedAt);
    }
}
