<?php

namespace App\Modules\Uploads\Services;

use GdImage;
use RuntimeException;

/** Gera as larguras em WebP usadas por capas, fotos e banners. */
class ImageVariants
{
    /** @return array{width: int, height: int} */
    public function dimensions(string $path): array
    {
        $size = getimagesize($path);

        if ($size === false) {
            throw new RuntimeException('Imagem ilegível.');
        }

        return ['width' => $size[0], 'height' => $size[1]];
    }

    /**
     * @param  list<int>  $widths
     * @return array<int, string> Conteúdo WebP por largura.
     */
    public function render(string $path, array $widths): array
    {
        $source = imagecreatefromstring((string) file_get_contents($path));

        if (! $source instanceof GdImage) {
            throw new RuntimeException('Imagem ilegível.');
        }

        $original = imagesx($source);
        $targets = array_values(array_unique(array_map(fn (int $w) => min($w, $original), $widths)));
        $variants = [];

        foreach ($targets as $width) {
            $resized = $width === $original ? $source : imagescale($source, $width, -1, IMG_BICUBIC);
            ob_start();
            imagewebp($resized, null, 82);
            $variants[$width] = (string) ob_get_clean();
        }

        return $variants;
    }
}
