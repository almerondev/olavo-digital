<?php

namespace App\Modules\LiveTv\Providers;

use App\Modules\LiveTv\Models\LiveChannel;
use App\Modules\Uploads\Enums\UploadPurpose;
use App\Modules\Uploads\Enums\UploadStatus;
use App\Modules\Uploads\Models\Upload;
use App\Modules\Uploads\Services\UploadStorage;
use App\Support\Http\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Permite exercitar a Olavo TV sem encoder nem conta de vídeo: usa um clipe local como sinal
 * e assina os webhooks com o segredo configurado, passando pelo mesmo caminho dos reais.
 */
class SimulatedLiveProvider implements LiveProvider
{
    private const CLIP_UUID = '00000000-0000-4000-8000-00000000c11b';

    public function __construct(private UploadStorage $storage)
    {
    }

    public function name(): string
    {
        return 'simulated';
    }

    public function createInput(string $name): array
    {
        return [
            'provider_input_id' => 'sim-'.Str::lower(Str::random(12)),
            'ingest_rtmps_url' => 'rtmps://simulado.invalid/live',
            'ingest_srt_url' => 'srt://simulado.invalid:778',
            'stream_key' => Str::random(32),
            'playback_ref' => null,
        ];
    }

    public function rotateKey(LiveChannel $channel): string
    {
        return Str::random(32);
    }

    public function playback(LiveChannel $channel): array
    {
        return ['kind' => 'file', 'url' => $this->storage->temporaryUrl($this->clip())];
    }

    public function parseWebhook(Request $request): LiveEvent
    {
        $expected = hash_hmac('sha256', $request->getContent(), (string) config('olavo.live.webhook_secret'));

        if (! hash_equals($expected, (string) $request->header('X-Live-Signature'))) {
            throw new ApiException('Assinatura inválida.', 'invalid_signature', 401);
        }

        $data = $request->validate([
            'type' => ['required', 'in:'.LiveEvent::CONNECTED.','.LiveEvent::DISCONNECTED.','.LiveEvent::RECORDING_READY],
            'input_id' => ['required', 'string'],
            'session' => ['required', 'string'],
        ]);

        return new LiveEvent(
            $data['type'],
            $data['input_id'],
            $data['session'],
            $data['type'] === LiveEvent::RECORDING_READY ? $this->recordingCopy()->id : null,
        );
    }

    public function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('olavo.live.webhook_secret'));
    }

    private function clip(): Upload
    {
        return Upload::where('uuid', self::CLIP_UUID)->first() ?? $this->store(self::CLIP_UUID);
    }

    private function recordingCopy(): Upload
    {
        return $this->store((string) Str::uuid());
    }

    private function store(string $uuid): Upload
    {
        $disk = config('olavo.uploads.private_disk');
        $key = "live/{$uuid}.mp4";
        $content = (string) file_get_contents(database_path('seeders/Demo/videos/atualidades.mp4'));
        Storage::disk($disk)->put($key, $content);

        return Upload::create([
            'uuid' => $uuid,
            'disk' => $disk,
            'object_key' => $key,
            'purpose' => UploadPurpose::Material,
            'file_class' => 'video',
            'original_name' => 'transmissao-simulada.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => strlen($content),
            'status' => UploadStatus::Ready,
        ]);
    }
}
