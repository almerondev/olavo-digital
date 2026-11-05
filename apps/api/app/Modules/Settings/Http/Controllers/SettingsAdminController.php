<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsService;
use App\Modules\Uploads\Models\Upload;
use App\Support\Audit\Audit;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsAdminController extends Controller
{
    /** Campo da tela => [chave, pública, padrão]. */
    private const FIELDS = [
        'name' => ['platform.name', true, 'Olavo Digital'],
        'tagline' => ['platform.tagline', true, null],
        'footer' => ['platform.footer', true, null],
        'brand_color' => ['platform.brand_color', true, null],
        'socials' => ['platform.socials', true, []],
        'terms' => ['legal.terms', true, null],
        'privacy' => ['legal.privacy', true, null],
        'auto_material' => ['notifications.auto_material', false, true],
        'auto_live' => ['notifications.auto_live', false, true],
    ];

    public function __construct(private SettingsService $settings)
    {
    }

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->present()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'footer' => ['nullable', 'string', 'max:300'],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
            'socials' => ['present', 'array'],
            'socials.instagram' => ['nullable', 'url:https', 'max:300'],
            'socials.youtube' => ['nullable', 'url:https', 'max:300'],
            'socials.facebook' => ['nullable', 'url:https', 'max:300'],
            'socials.tiktok' => ['nullable', 'url:https', 'max:300'],
            'terms' => ['nullable', 'string', 'max:60000'],
            'privacy' => ['nullable', 'string', 'max:60000'],
            'auto_material' => ['required', 'boolean'],
            'auto_live' => ['required', 'boolean'],
        ], [], ['name' => 'nome', 'brand_color' => 'cor', 'tagline' => 'frase', 'footer' => 'rodapé']);

        $legalChanged = ($data['terms'] ?? null) !== $this->settings->get('legal.terms')
            || ($data['privacy'] ?? null) !== $this->settings->get('legal.privacy');

        foreach (self::FIELDS as $field => [$key, $public]) {
            $this->settings->set($key, $data[$field] ?? null, $public);
        }

        $this->saveLogo($data['logo_upload_id'] ?? null);

        // Texto novo pede novo aceite; sem texto nenhum, não há o que aceitar.
        if ($legalChanged) {
            $this->settings->set('legal.version', ($data['terms'] ?? $data['privacy'] ?? null) ? now()->format('YmdHis') : null, true);
        }

        Audit::record('settings.updated', null, ['legal_changed' => $legalChanged]);

        return response()->json(['data' => $this->present()]);
    }

    private function saveLogo(?int $uploadId): void
    {
        $urls = [];

        if ($uploadId) {
            $urls = Upload::find($uploadId)?->imageUrls() ?? [];

            if ($urls === []) {
                throw new ApiException('A logo precisa ser uma imagem já processada.', 'logo_not_ready', 422);
            }
        }

        $this->settings->set('platform.logo_upload_id', $uploadId);
        $this->settings->set('platform.logo_urls', $urls, true);
    }

    private function present(): array
    {
        $values = [];

        foreach (self::FIELDS as $field => [$key, , $default]) {
            $values[$field] = $this->settings->get($key, $default);
        }

        return [
            ...$values,
            'logo_upload_id' => $this->settings->get('platform.logo_upload_id'),
            'logo_urls' => (object) ($this->settings->get('platform.logo_urls') ?? []),
            'legal_version' => $this->settings->get('legal.version'),
            // Só o estado: chaves e senhas ficam no ambiente do servidor.
            'environment' => [
                'storage' => config('olavo.uploads.driver'),
                'mail' => config('mail.default'),
                'queue' => config('queue.default'),
                'push' => false,
            ],
        ];
    }
}
