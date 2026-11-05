<?php

namespace App\Modules\Access\Concerns;

use App\Modules\Access\Models\AudienceTarget;
use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/** Motor único de público: materiais, categorias, seções, banners e avisos usam a mesma regra. */
trait HasAudience
{
    public function audienceTargets(): MorphMany
    {
        return $this->morphMany(AudienceTarget::class, 'targetable');
    }

    /**
     * @param  list<int>  $groupIds
     * @param  list<int>  $studentIds
     */
    public function syncAudience(array $groupIds, array $studentIds = []): void
    {
        $this->audienceTargets()->delete();

        foreach (array_unique($groupIds) as $groupId) {
            $this->audienceTargets()->create(['student_group_id' => $groupId]);
        }

        foreach (array_unique($studentIds) as $studentId) {
            $this->audienceTargets()->create(['student_id' => $studentId]);
        }
    }

    /**
     * Para conteúdo já carregado, como a estrutura da home em cache. Sem destinos: vale para todos.
     *
     * @param  Collection<int, int>  $groupIds
     */
    public function reaches(?int $studentId, Collection $groupIds): bool
    {
        if ($this->audienceTargets->isEmpty()) {
            return true;
        }

        return $this->audienceTargets->contains(fn (AudienceTarget $target) => ($studentId && $target->student_id === $studentId)
            || $groupIds->contains($target->student_group_id));
    }

    public function scopeTargeting(Builder $query, User $user): Builder
    {
        $student = $user->student;

        if (! $student) {
            return $query->whereRaw('1 = 0');
        }

        $groupIds = $user->audienceGroupIds();

        return $query->whereHas('audienceTargets', function (Builder $targets) use ($student, $groupIds) {
            $targets->where('student_id', $student->id)->orWhereIn('student_group_id', $groupIds);
        });
    }
}
