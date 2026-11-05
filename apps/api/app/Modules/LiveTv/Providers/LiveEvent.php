<?php

namespace App\Modules\LiveTv\Providers;

/** Evento de transmissão em formato único, seja qual for o provedor de vídeo. */
final readonly class LiveEvent
{
    public const CONNECTED = 'connected';
    public const DISCONNECTED = 'disconnected';
    public const RECORDING_READY = 'recording_ready';

    public function __construct(
        public string $type,
        public string $inputId,
        public string $sessionRef,
        public ?int $recordingUploadId = null,
    ) {
    }
}
