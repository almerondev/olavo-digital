<?php

namespace App\Modules\Uploads\Services;

use App\Modules\Uploads\Models\Upload;
use Illuminate\Support\Facades\Storage;

class S3UploadStorage implements UploadStorage
{
    public function uploadTarget(Upload $upload): array
    {
        $target = Storage::disk($upload->disk)->temporaryUploadUrl($upload->object_key, now()->addMinutes(30));

        return [
            'url' => $target['url'],
            'method' => 'PUT',
            'headers' => collect($target['headers'])->map(fn ($value) => is_array($value) ? implode(',', $value) : $value)->all(),
        ];
    }

    public function temporaryUrl(Upload $upload): string
    {
        return Storage::disk($upload->disk)->temporaryUrl(
            $upload->object_key,
            now()->addMinutes(config('olavo.uploads.url_ttl_minutes')),
        );
    }
}
