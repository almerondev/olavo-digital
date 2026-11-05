<?php

namespace App\Modules\Home\Services;

use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Models\Material;
use App\Modules\Uploads\Services\UploadStorage;
use App\Support\YouTubeUrl;

/** Define como o hero toca a prévia de um vídeo. Conteúdo que não é vídeo fica só com a imagem. */
class HeroPreview
{
    public function __construct(private UploadStorage $storage)
    {
    }

    /** @return array{kind: string, url: string}|null */
    public function for(Material $material): ?array
    {
        return match ($material->type) {
            MaterialType::Video => $this->uploaded($material),
            MaterialType::ExternalVideo => $this->embedded((string) $material->external_url),
            default => null,
        };
    }

    /** @return array{kind: string, url: string}|null */
    private function uploaded(Material $material): ?array
    {
        $upload = $material->primaryFile?->upload;

        return $upload?->isReady() ? ['kind' => 'file', 'url' => $this->storage->temporaryUrl($upload)] : null;
    }

    /** @return array{kind: string, url: string}|null */
    private function embedded(string $url): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $id = YouTubeUrl::id($url);

        if ($id) {
            return ['kind' => 'embed', 'url' => YouTubeUrl::embed($url, ['autoplay' => 1, 'mute' => 1, 'controls' => 0, 'loop' => 1, 'playlist' => $id, 'playsinline' => 1])];
        }

        if (str_ends_with($host, 'vimeo.com') && $path !== '') {
            return ['kind' => 'embed', 'url' => 'https://player.vimeo.com/video/'.basename($path).'?background=1&muted=1'];
        }

        return null;
    }
}
