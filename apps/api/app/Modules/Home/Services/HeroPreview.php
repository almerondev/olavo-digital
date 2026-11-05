<?php

namespace App\Modules\Home\Services;

use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Models\Material;
use App\Modules\Uploads\Services\UploadStorage;

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
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (str_ends_with($host, 'youtu.be') || str_ends_with($host, 'youtube.com')) {
            $id = str_ends_with($host, 'youtu.be') ? $path : ($query['v'] ?? null);

            return $id ? ['kind' => 'embed', 'url' => "https://www.youtube-nocookie.com/embed/{$id}?autoplay=1&mute=1&controls=0&loop=1&playlist={$id}&playsinline=1"] : null;
        }

        if (str_ends_with($host, 'vimeo.com') && $path !== '') {
            return ['kind' => 'embed', 'url' => 'https://player.vimeo.com/video/'.basename($path).'?background=1&muted=1'];
        }

        return null;
    }
}
