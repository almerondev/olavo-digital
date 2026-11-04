<?php

namespace App\Modules\Uploads\Console;

use App\Modules\Uploads\Enums\UploadStatus;
use App\Modules\Uploads\Models\Upload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneUploads extends Command
{
    protected $signature = 'uploads:prune';

    protected $description = 'Remove envios abandonados ou recusados há mais de 24 horas';

    public function handle(): int
    {
        $removed = 0;

        Upload::query()
            ->whereIn('status', [UploadStatus::Pending, UploadStatus::Rejected])
            ->where('updated_at', '<', now()->subDay())
            ->chunkById(200, function ($uploads) use (&$removed) {
                foreach ($uploads as $upload) {
                    Storage::disk($upload->disk)->delete($upload->object_key);
                    $upload->delete();
                    $removed++;
                }
            });

        $this->info("{$removed} envios removidos.");

        return self::SUCCESS;
    }
}
