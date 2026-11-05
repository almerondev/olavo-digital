<?php

namespace App\Modules\Uploads\Models;

use App\Modules\Uploads\Enums\UploadPurpose;
use App\Modules\Uploads\Enums\UploadStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['uuid', 'disk', 'object_key', 'purpose', 'file_class', 'original_name', 'mime_type', 'size_bytes', 'checksum', 'status', 'rejection_reason', 'width', 'height', 'meta', 'uploaded_by'])]
class Upload extends Model
{
    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'purpose' => UploadPurpose::class,
            'status' => UploadStatus::class,
            'meta' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isReady(): bool
    {
        return $this->status === UploadStatus::Ready;
    }

    /** @return array<int|string, string> URLs públicas por largura; vazio para arquivos privados. */
    public function imageUrls(): array
    {
        if (! $this->purpose->isPublicImage() || ! $this->isReady()) {
            return [];
        }

        $disk = Storage::disk($this->disk);

        return collect($this->meta['variants'] ?? [])
            ->map(fn (string $key) => $disk->url($key))
            ->all();
    }
}
