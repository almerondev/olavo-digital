<?php

namespace App\Modules\Uploads\Services;

use App\Modules\Uploads\Models\Upload;

/** Separa o fluxo de upload do destino físico: disco local em desenvolvimento, R2 em produção. */
interface UploadStorage
{
    /** @return array{url: string, method: string, headers: array<string, string>} */
    public function uploadTarget(Upload $upload): array;

    public function temporaryUrl(Upload $upload): string;
}
