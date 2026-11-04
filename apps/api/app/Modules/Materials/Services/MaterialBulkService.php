<?php

namespace App\Modules\Materials\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Materials\Models\Material;
use App\Support\Audit\Audit;
use App\Support\Http\ApiException;
use Illuminate\Validation\ValidationException;

class MaterialBulkService
{
    public function __construct(private MaterialWorkflow $workflow, private MaterialService $materials)
    {
    }

    /**
     * Cada material é tratado sozinho: um item inválido não impede os demais.
     *
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $options
     * @return array{processed: int, failed: list<array{id: int, title: string, reason: string}>}
     */
    public function run(string $action, array $ids, array $options, User $actor): array
    {
        $processed = 0;
        $failed = [];

        foreach (Material::with('categories')->whereIn('id', $ids)->get() as $material) {
            try {
                $this->apply($action, $material, $options, $actor);
                $processed++;
            } catch (ApiException|ValidationException $e) {
                $reason = $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage();
                $failed[] = ['id' => $material->id, 'title' => $material->title, 'reason' => $reason];
            }
        }

        Audit::record('material.bulk', null, ['action' => $action, 'ids' => $ids, 'processed' => $processed], $actor);

        return ['processed' => $processed, 'failed' => $failed];
    }

    /** @param array<string, mixed> $options */
    private function apply(string $action, Material $material, array $options, User $actor): void
    {
        match ($action) {
            'publish' => $this->workflow->release($material, $actor),
            'archive' => $this->workflow->archive($material, $actor),
            'delete' => $this->materials->delete($material, $actor),
            'set_teacher' => $material->update(['teacher_id' => $options['teacher_id']]),
            'add_category' => $material->categories()->syncWithoutDetaching([$options['category_id'] => ['is_primary' => $material->categories->isEmpty()]]),
        };
    }
}
