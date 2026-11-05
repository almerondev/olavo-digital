<?php

namespace Database\Seeders\Demo;

use App\Modules\Uploads\Enums\UploadPurpose;
use App\Modules\Uploads\Enums\UploadStatus;
use App\Modules\Uploads\Models\Upload;
use App\Modules\Uploads\Services\ImageVariants;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Gera arquivos de verdade para os dados de demonstração: capas, imagens e PDFs. */
class DemoAssets
{
    public function __construct(private ImageVariants $images)
    {
    }

    /** Capa vertical dos cards. @param array{0: int, 1: int, 2: int} $color */
    public function cover(string $name, array $color, int $seed): Upload
    {
        return $this->publicImage($name, $color, $seed, 1000, 1500);
    }

    /** Banner horizontal do hero e da página de detalhes. @param array{0: int, 1: int, 2: int} $color */
    public function banner(string $name, array $color, int $seed): Upload
    {
        return $this->publicImage($name, $color, $seed, 1920, 1080);
    }

    /** Clipe curto gerado para a demonstração, em database/seeders/Demo/videos. */
    public function video(string $clip, string $title): Upload
    {
        return $this->storePrivate(Str::slug($title).'.mp4', 'video', 'video/mp4', (string) file_get_contents(__DIR__."/videos/{$clip}.mp4"));
    }

    /** @param array{0: int, 1: int, 2: int} $color */
    private function publicImage(string $name, array $color, int $seed, int $width, int $height): Upload
    {
        $path = tempnam(sys_get_temp_dir(), 'cov').'.png';
        imagepng($this->artwork($width, $height, $color, $seed), $path);

        $uuid = (string) Str::uuid();
        $disk = config('olavo.uploads.public_disk');
        $variants = [];

        foreach ($this->images->render($path, config('olavo.uploads.image_widths')) as $width => $content) {
            $key = "images/{$uuid}/{$width}.webp";
            Storage::disk($disk)->put($key, $content, 'public');
            $variants[$width] = $key;
        }

        unlink($path);

        return Upload::create([
            'uuid' => $uuid,
            'disk' => $disk,
            'object_key' => end($variants),
            'purpose' => UploadPurpose::Image,
            'file_class' => 'image',
            'original_name' => "{$name}.png",
            'mime_type' => 'image/webp',
            'status' => UploadStatus::Ready,
            'width' => $width,
            'height' => $height,
            'meta' => ['variants' => $variants],
        ]);
    }

    /** @param array{0: int, 1: int, 2: int} $color */
    public function privateImage(string $name, array $color, int $seed): Upload
    {
        ob_start();
        imagepng($this->artwork(1600, 1200, $color, $seed));
        $content = (string) ob_get_clean();

        return $this->storePrivate("{$name}.png", 'image', 'image/png', $content, ['width' => 1600, 'height' => 1200]);
    }

    /** @param list<string> $paragraphs */
    public function pdf(string $title, array $paragraphs): Upload
    {
        return $this->storePrivate(Str::slug($title).'.pdf', 'pdf', 'application/pdf', $this->buildPdf($title, $paragraphs));
    }

    /** @param array<string, mixed> $extra */
    private function storePrivate(string $name, string $class, string $mime, string $content, array $extra = []): Upload
    {
        $uuid = (string) Str::uuid();
        $key = "materials/{$uuid}/{$name}";
        Storage::disk(config('olavo.uploads.private_disk'))->put($key, $content);

        return Upload::create($extra + [
            'uuid' => $uuid,
            'disk' => config('olavo.uploads.private_disk'),
            'object_key' => $key,
            'purpose' => UploadPurpose::Material,
            'file_class' => $class,
            'original_name' => $name,
            'mime_type' => $mime,
            'size_bytes' => strlen($content),
            'checksum' => hash('sha256', $content),
            'status' => UploadStatus::Ready,
        ]);
    }

    /** Arte abstrata com a cor da matéria, para as capas não serem blocos lisos. */
    private function artwork(int $width, int $height, array $color, int $seed): \GdImage
    {
        mt_srand($seed);
        $image = imagecreatetruecolor($width, $height);
        [$r, $g, $b] = $color;

        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $line = imagecolorallocate($image, (int) ($r * (0.75 - 0.6 * $t)), (int) ($g * (0.75 - 0.6 * $t)), (int) ($b * (0.75 - 0.6 * $t)));
            imageline($image, 0, $y, $width, $y, $line);
        }

        imagealphablending($image, true);

        for ($i = 0; $i < 14; $i++) {
            $size = mt_rand((int) ($height * 0.25), (int) ($height * 1.1));
            $tone = imagecolorallocatealpha($image, min(255, $r + 50), min(255, $g + 50), min(255, $b + 50), mt_rand(105, 122));
            imagefilledellipse($image, mt_rand(0, $width), mt_rand(0, $height), $size, $size, $tone);
        }

        for ($i = 0; $i < 3; $i++) {
            $stroke = imagecolorallocatealpha($image, 255, 255, 255, 112);
            imagesetthickness($image, mt_rand(3, 9));
            imageline($image, mt_rand(0, $width), 0, mt_rand(0, $width), $height, $stroke);
        }

        return $image;
    }

    /** PDF mínimo e válido, com Helvetica em WinAnsi. @param list<string> $paragraphs */
    private function buildPdf(string $title, array $paragraphs): string
    {
        $escape = fn (string $text) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) iconv('UTF-8', 'CP1252//TRANSLIT', $text));
        $pages = array_chunk($paragraphs, 2);
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        $next = 4;

        foreach ($pages as $index => $page) {
            $lines = "BT /F1 22 Tf 60 770 Td (".$escape($title).") Tj ET\n";
            $y = 730;

            foreach ($page as $paragraph) {
                foreach (explode("\n", wordwrap($paragraph, 82)) as $line) {
                    $lines .= "BT /F1 12 Tf 60 {$y} Td (".$escape($line).") Tj ET\n";
                    $y -= 18;
                }
                $y -= 14;
            }

            $lines .= 'BT /F1 9 Tf 60 40 Td (Material de demonstracao - pagina '.($index + 1).' de '.count($pages).") Tj ET\n";
            $pageId = $next++;
            $contentId = $next++;
            $kids[] = "{$pageId} 0 R";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($lines)." >>\nstream\n{$lines}endstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
