<?php

namespace App\Modules\Uploads\Services;

use App\Modules\Uploads\Models\Upload;
use Illuminate\Support\Facades\URL;

class LocalUploadStorage implements UploadStorage
{
    public function uploadTarget(Upload $upload): array
    {
        return [
            'url' => URL::temporarySignedRoute('uploads.content', now()->addMinutes(30), ['upload' => $upload]),
            'method' => 'PUT',
            'headers' => [],
        ];
    }

    public function temporaryUrl(Upload $upload): string
    {
        return URL::temporarySignedRoute(
            'uploads.file',
            now()->addMinutes(config('olavo.uploads.url_ttl_minutes')),
            ['upload' => $upload],
        );
    }
}
