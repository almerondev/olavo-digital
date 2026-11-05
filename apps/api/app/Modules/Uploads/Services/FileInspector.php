<?php

namespace App\Modules\Uploads\Services;

use finfo;
use ZipArchive;

/** Confere o conteúdo real do arquivo; extensão e tipo informados pelo navegador não são confiáveis. */
class FileInspector
{
    private const OOXML_FOLDERS = ['docx' => 'word/', 'pptx' => 'ppt/', 'xlsx' => 'xl/'];
    private const OOXML_MIMES = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
    private const OLE_MIMES = [
        'doc' => 'application/msword',
        'ppt' => 'application/vnd.ms-powerpoint',
        'xls' => 'application/vnd.ms-excel',
    ];

    public function mimeOf(string $path, string $extension): string
    {
        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';

        if ($extension === 'vtt') {
            return str_starts_with(ltrim((string) file_get_contents($path, false, null, 0, 16), "\xEF\xBB\xBF"), 'WEBVTT') ? 'text/vtt' : $detected;
        }

        if (isset(self::OOXML_FOLDERS[$extension])) {
            return $this->isOoxml($path, $extension) ? self::OOXML_MIMES[$extension] : $detected;
        }

        // finfo reconhece o contêiner OLE, mas nem sempre o programa de origem.
        if (isset(self::OLE_MIMES[$extension]) && in_array($detected, ['application/CDFV2', 'application/x-ole-storage', self::OLE_MIMES[$extension]], true)) {
            return self::OLE_MIMES[$extension];
        }

        return $detected;
    }

    private function isOoxml(string $path, string $extension): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return false;
        }

        $valid = $zip->locateName('[Content_Types].xml') !== false;
        $hasFolder = false;

        for ($i = 0; $valid && ! $hasFolder && $i < $zip->numFiles; $i++) {
            $hasFolder = str_starts_with((string) $zip->getNameIndex($i), self::OOXML_FOLDERS[$extension]);
        }

        $zip->close();

        return $valid && $hasFolder;
    }
}
