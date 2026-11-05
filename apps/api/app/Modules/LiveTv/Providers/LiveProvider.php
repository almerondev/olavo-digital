<?php

namespace App\Modules\LiveTv\Providers;

use App\Modules\LiveTv\Models\LiveChannel;
use Illuminate\Http\Request;

/** Tudo que depende do fornecedor de vídeo. Trocar de fornecedor é escrever outra implementação. */
interface LiveProvider
{
    public function name(): string;

    /** @return array{provider_input_id: string, ingest_rtmps_url: string|null, ingest_srt_url: string|null, stream_key: string, playback_ref: string|null} */
    public function createInput(string $name): array;

    public function rotateKey(LiveChannel $channel): string;

    /** @return array{kind: string, url: string} */
    public function playback(LiveChannel $channel): array;

    /** Valida a autenticidade do webhook; recusa com exceção. */
    public function parseWebhook(Request $request): LiveEvent;
}
