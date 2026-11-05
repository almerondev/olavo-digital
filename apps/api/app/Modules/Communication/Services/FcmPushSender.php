<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Contracts\PushSender;
use App\Modules\Communication\Models\PlatformNotification;
use App\Modules\Communication\Models\PushDevice;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Envia pelo Firebase Cloud Messaging. Quem recebe já foi filtrado por curso, turma e preferências. */
class FcmPushSender implements PushSender
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const BATCH = 100;
    private const TOKEN_MINUTES = 50;

    /** @param array{project_id: string, client_email: string, private_key: string, token_uri?: string} $credentials */
    public function __construct(private array $credentials)
    {
    }

    public function send(PlatformNotification $notification, array $userIds): void
    {
        PushDevice::whereIn('user_id', $userIds)
            ->pluck('token', 'id')
            ->chunk(self::BATCH)
            ->each(fn (Collection $devices) => $this->deliver($notification, $devices));
    }

    /** @param Collection<int, string> $devices */
    private function deliver(PlatformNotification $notification, Collection $devices): void
    {
        $url = "https://fcm.googleapis.com/v1/projects/{$this->credentials['project_id']}/messages:send";
        $bearer = $this->accessToken();

        $responses = Http::pool(fn (Pool $pool) => $devices->map(
            fn (string $token, int $id) => $pool->as((string) $id)->withToken($bearer)->post($url, ['message' => $this->message($notification, $token)])
        )->all());

        foreach ($responses as $id => $response) {
            if (! $response instanceof Response) {
                Log::warning('Push sem resposta do Firebase.', ['device' => $id]);
            } elseif ($this->tokenIsDead($response)) {
                PushDevice::whereKey($id)->delete();
            } elseif ($response->failed()) {
                Log::warning('Push recusado pelo Firebase.', ['device' => $id, 'status' => $response->status()]);
            }
        }
    }

    private function message(PlatformNotification $notification, string $token): array
    {
        return [
            'token' => $token,
            'notification' => ['title' => $notification->title, 'body' => $notification->body],
            'data' => ['url' => (string) $notification->url, 'type' => $notification->type],
            'android' => ['priority' => 'HIGH', 'notification' => ['channel_id' => 'avisos', 'sound' => 'default']],
        ];
    }

    /** App desinstalado ou token trocado: não adianta tentar de novo. */
    private function tokenIsDead(Response $response): bool
    {
        return $response->status() === 404 || str_contains($response->body(), 'UNREGISTERED');
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm:access-token', now()->addMinutes(self::TOKEN_MINUTES), function () {
            $response = Http::asForm()->post($this->credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->assertion(),
            ]);

            return $response->throw()->json('access_token');
        });
    }

    private function assertion(): string
    {
        $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $issuedAt = time();

        $unsigned = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode([
            'iss' => $this->credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => $this->credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ]));

        if (! openssl_sign($unsigned, $signature, $this->credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Não foi possível assinar o pedido ao Firebase.');
        }

        return $unsigned.'.'.$encode($signature);
    }
}
