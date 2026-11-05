<?php

namespace App\Modules\Uploads\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Uploads\Enums\UploadPurpose;
use App\Modules\Uploads\Enums\UploadStatus;
use App\Modules\Uploads\Jobs\ProcessUpload;
use App\Modules\Uploads\Models\Upload;
use App\Support\Http\ApiException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadService
{
    public function __construct(private UploadStorage $storage)
    {
    }

    /** @return array{upload: Upload, target: array{url: string, method: string, headers: array<string, string>}} */
    public function start(User $user, UploadPurpose $purpose, string $name, int $size): array
    {
        $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));
        $class = $this->classFor($extension);

        if (! $class || ($purpose->isPublicImage() && $class !== 'image')) {
            throw ValidationException::withMessages(['name' => 'Este formato de arquivo não é permitido.']);
        }

        $limit = $this->limitInBytes($class);

        if ($size > $limit) {
            $mb = config("olavo.uploads.classes.{$class}.max_mb");

            throw ValidationException::withMessages(['size' => "Este arquivo excede o limite de {$mb} MB."]);
        }

        $uuid = (string) Str::uuid();
        $upload = Upload::create([
            'uuid' => $uuid,
            'disk' => config('olavo.uploads.private_disk'),
            'object_key' => "incoming/{$uuid}.{$extension}",
            'purpose' => $purpose,
            'file_class' => $class,
            'original_name' => Str::limit($name, 240, ''),
            'uploaded_by' => $user->id,
        ]);

        return ['upload' => $upload, 'target' => $this->storage->uploadTarget($upload)];
    }

    public function complete(Upload $upload): Upload
    {
        if ($upload->status !== UploadStatus::Pending) {
            throw new ApiException('Este arquivo já foi enviado.', 'upload_already_completed', 409);
        }

        if (! Storage::disk($upload->disk)->exists($upload->object_key)) {
            throw new ApiException('O arquivo não chegou ao armazenamento. Envie novamente.', 'upload_missing', 409);
        }

        $upload->update(['status' => UploadStatus::Processing]);
        ProcessUpload::dispatch($upload);

        return $upload;
    }

    public function limitInBytes(string $class): int
    {
        return (int) config("olavo.uploads.classes.{$class}.max_mb") * 1024 * 1024;
    }

    private function classFor(string $extension): ?string
    {
        foreach (config('olavo.uploads.classes') as $class => $rules) {
            if (in_array($extension, $rules['extensions'], true)) {
                return $class;
            }
        }

        return null;
    }
}
