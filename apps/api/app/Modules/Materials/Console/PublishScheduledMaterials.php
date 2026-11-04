<?php

namespace App\Modules\Materials\Console;

use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialWorkflow;
use App\Support\Http\ApiException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishScheduledMaterials extends Command
{
    protected $signature = 'materials:publish-scheduled';

    protected $description = 'Publica os materiais cujo agendamento venceu';

    public function handle(MaterialWorkflow $workflow): int
    {
        $ids = Material::where('status', MaterialStatus::Scheduled)->where('publish_at', '<=', now())->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $workflow) {
                $material = Material::lockForUpdate()->find($id);

                if ($material?->status !== MaterialStatus::Scheduled) {
                    return;
                }

                try {
                    $workflow->publish($material, null);
                } catch (ApiException|ValidationException $e) {
                    // Ficou incompleto depois de agendado: volta para rascunho e fica visível na biblioteca.
                    $material->update(['status' => MaterialStatus::Draft, 'review_note' => 'Agendamento cancelado: material incompleto.']);
                }
            });
        }

        return self::SUCCESS;
    }
}
