<?php

namespace App\Modules\Uploads\Jobs;

use App\Modules\Uploads\Enums\UploadStatus;
use App\Modules\Uploads\Models\Upload;
use App\Modules\Uploads\Services\FileInspector;
use App\Modules\Uploads\Services\ImageVariants;
use App\Modules\Uploads\Services\UploadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessUpload implements ShouldQueue
{
    use Dispatchable, Queueable;

    private const HEAD_BYTES = 1048576;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(public Upload $upload)
    {
    }

    public function handle(FileInspector $inspector, ImageVariants $images, UploadService $uploads): void
    {
        $upload = $this->upload;

        if ($upload->status !== UploadStatus::Processing) {
            return;
        }

        $disk = Storage::disk($upload->disk);
        $size = $disk->size($upload->object_key);
        $extension = pathinfo($upload->object_key, PATHINFO_EXTENSION);
        $rules = config("olavo.uploads.classes.{$upload->file_class}");

        if ($size > $uploads->limitInBytes($upload->file_class)) {
            $this->reject("O arquivo excede o limite de {$rules['max_mb']} MB.");

            return;
        }

        $local = $this->copyToTemp($extension);

        try {
            $mime = $inspector->mimeOf($local, $extension);

            if (! in_array($mime, $rules['mimes'], true)) {
                $this->reject('O conteúdo do arquivo não corresponde ao formato informado.');

                return;
            }

            $attributes = ['mime_type' => $mime, 'size_bytes' => $size, 'status' => UploadStatus::Ready];

            if ($this->needsWholeFile()) {
                $attributes['checksum'] = hash_file('sha256', $local);
            }

            if ($upload->file_class === 'image') {
                $attributes += $images->dimensions($local);
            }

            $attributes += $upload->purpose->isPublicImage()
                ? $this->publishImage($local, $extension, $images)
                : $this->moveToPrivateFolder($extension);

            $upload->update($attributes);
        } catch (Throwable $e) {
            report($e);
            $this->reject('Não foi possível processar este arquivo.');
        } finally {
            @unlink($local);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->reject('Não foi possível processar este arquivo.');
    }

    private function needsWholeFile(): bool
    {
        return ! in_array($this->upload->file_class, ['video', 'audio', 'pdf'], true);
    }

    /** Vídeo, áudio e PDF só precisam do início do arquivo para a conferência de formato. */
    private function copyToTemp(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'upl').'.'.$extension;
        $stream = Storage::disk($this->upload->disk)->readStream($this->upload->object_key);
        $target = fopen($path, 'wb');

        if ($this->needsWholeFile()) {
            stream_copy_to_stream($stream, $target);
        } else {
            stream_copy_to_stream($stream, $target, self::HEAD_BYTES);
        }

        fclose($target);
        fclose($stream);

        return $path;
    }

    /** @return array<string, mixed> */
    private function moveToPrivateFolder(string $extension): array
    {
        $key = "materials/{$this->upload->uuid}/".Str::slug(pathinfo($this->upload->original_name, PATHINFO_FILENAME)).".{$extension}";
        Storage::disk($this->upload->disk)->move($this->upload->object_key, $key);

        return ['object_key' => $key];
    }

    /** @return array<string, mixed> */
    private function publishImage(string $local, string $extension, ImageVariants $images): array
    {
        $public = Storage::disk(config('olavo.uploads.public_disk'));
        $folder = "images/{$this->upload->uuid}";
        $variants = [];

        foreach ($images->render($local, config('olavo.uploads.image_widths')) as $width => $content) {
            $key = "{$folder}/{$width}.webp";
            $public->put($key, $content, 'public');
            $variants[$width] = $key;
        }

        Storage::disk($this->upload->disk)->delete($this->upload->object_key);

        return [
            'disk' => config('olavo.uploads.public_disk'),
            'object_key' => array_values($variants)[array_key_last(array_values($variants))],
            'meta' => ['variants' => $variants],
        ];
    }

    private function reject(string $reason): void
    {
        $this->upload->update(['status' => UploadStatus::Rejected, 'rejection_reason' => $reason]);

        if ($this->upload->disk === config('olavo.uploads.private_disk')) {
            Storage::disk($this->upload->disk)->delete($this->upload->object_key);
        }
    }
}
