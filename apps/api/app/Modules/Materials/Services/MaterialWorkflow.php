<?php

namespace App\Modules\Materials\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Materials\Enums\MaterialStatus as Status;
use App\Modules\Materials\Events\MaterialPublished;
use App\Modules\Materials\Models\Material;
use App\Support\Audit\Audit;
use App\Support\Http\ApiException;
use Illuminate\Validation\ValidationException;

/** Único ponto que muda o status de um material. O evento de publicação dispara uma só vez. */
class MaterialWorkflow
{
    /** Botão "Publicar": publica, agenda ou envia para revisão conforme a permissão de quem clicou. */
    public function release(Material $material, User $actor): Material
    {
        if (! $actor->can('publish', $material)) {
            return $this->submit($material, $actor);
        }

        return $material->publish_at?->isFuture()
            ? $this->schedule($material, $actor)
            : $this->publish($material, $actor);
    }

    public function submit(Material $material, User $actor): Material
    {
        $this->assertFrom($material, [Status::Draft]);
        $this->assertComplete($material);

        return $this->move($material, Status::PendingReview, $actor, 'material.submitted');
    }

    public function publish(Material $material, ?User $actor): Material
    {
        $this->assertFrom($material, [Status::Draft, Status::PendingReview, Status::Scheduled]);
        $this->assertComplete($material);

        $firstPublication = $material->published_at === null;

        $this->move($material, Status::Published, $actor, 'material.published', [
            'published_at' => $material->published_at ?? now(),
            'archived_at' => null,
            'review_note' => null,
        ]);

        if ($firstPublication) {
            MaterialPublished::dispatch($material);
        }

        return $material;
    }

    public function schedule(Material $material, User $actor): Material
    {
        $this->assertFrom($material, [Status::Draft, Status::PendingReview]);
        $this->assertComplete($material);

        if (! $material->publish_at?->isFuture()) {
            throw ValidationException::withMessages(['publish_at' => 'Escolha uma data futura para o agendamento.']);
        }

        return $this->move($material, Status::Scheduled, $actor, 'material.scheduled');
    }

    public function reject(Material $material, User $actor, string $note): Material
    {
        $this->assertFrom($material, [Status::PendingReview]);

        return $this->move($material, Status::Draft, $actor, 'material.rejected', [
            'review_note' => $note,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
        ]);
    }

    public function unschedule(Material $material, User $actor): Material
    {
        $this->assertFrom($material, [Status::Scheduled]);

        return $this->move($material, Status::Draft, $actor, 'material.unscheduled', ['publish_at' => null]);
    }

    public function archive(Material $material, User $actor): Material
    {
        $this->assertFrom($material, [Status::Published]);

        return $this->move($material, Status::Archived, $actor, 'material.archived', ['archived_at' => now()]);
    }

    public function restore(Material $material, User $actor): Material
    {
        $this->assertFrom($material, [Status::Archived]);

        return $this->move($material, Status::Published, $actor, 'material.restored', ['archived_at' => null]);
    }

    /** @param list<Status> $allowed */
    private function assertFrom(Material $material, array $allowed): void
    {
        if (! in_array($material->status, $allowed, true)) {
            throw new ApiException('Esta ação não está disponível no status atual do material.', 'invalid_transition', 409);
        }
    }

    private function assertComplete(Material $material): void
    {
        $material->loadMissing('primaryFile.upload', 'categories');
        $errors = [];

        if (! $material->teacher_id) {
            $errors['teacher_id'] = 'Defina o professor responsável.';
        }

        if ($material->categories->isEmpty()) {
            $errors['category_ids'] = 'Selecione ao menos uma categoria.';
        }

        if ($material->type->requiresUrl()) {
            if (! $material->external_url) {
                $errors['external_url'] = 'Informe o endereço do conteúdo.';
            }
        } elseif (! $material->primaryFile) {
            $errors['file_upload_id'] = 'Envie o arquivo do material.';
        } elseif (! $material->primaryFile->upload->isReady()) {
            $errors['file_upload_id'] = 'Aguarde o processamento do arquivo.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function move(Material $material, Status $to, ?User $actor, string $action, array $attributes = []): Material
    {
        $from = $material->status;
        $material->update(['status' => $to] + $attributes);
        Audit::record($action, $material, ['from' => $from->value, 'to' => $to->value], $actor);

        return $material;
    }
}
