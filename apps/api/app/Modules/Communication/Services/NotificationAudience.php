<?php

namespace App\Modules\Communication\Services;

use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\User;
use App\Modules\Communication\Models\Announcement;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\LiveTv\Services\LiveDirectory;
use App\Modules\Materials\Models\Material;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Transforma o destino da notificação em alunos. Conteúdo reaproveita a regra de
 * visibilidade de cada módulo, para o aviso nunca chegar a quem não pode abrir o link.
 */
class NotificationAudience
{
    private const CHUNK = 500;

    public function __construct(private LiveDirectory $live)
    {
    }

    /**
     * @param  array{mode: string, ids?: list<int>}  $audience
     * @param  Closure(list<int>): void  $handle
     */
    public function eachChunk(array $audience, Closure $handle): void
    {
        $ids = $audience['ids'] ?? [];
        $reaches = $this->reachFor($audience['mode'], $ids);

        $this->query($audience['mode'], $ids)->chunkById(self::CHUNK, function (Collection $users) use ($reaches, $handle) {
            $userIds = $users->filter($reaches)->modelKeys();

            if ($userIds !== []) {
                $handle($userIds);
            }
        });
    }

    /** @param list<int> $ids */
    private function query(string $mode, array $ids): Builder
    {
        return User::query()
            ->with('student')
            ->where('status', UserStatus::Active)
            ->whereHas('student')
            ->when($mode === 'course', fn (Builder $query) => $query->whereHas('student.courses', fn (Builder $courses) => $courses->whereIn('courses.id', $ids)))
            ->when($mode === 'group', fn (Builder $query) => $query->whereHas('student.groups', fn (Builder $groups) => $groups->whereIn('student_groups.id', $ids)))
            ->when($mode === 'user', fn (Builder $query) => $query->whereIn('users.id', $ids));
    }

    /** @param list<int> $ids */
    private function reachFor(string $mode, array $ids): Closure
    {
        $id = $ids[0] ?? 0;

        return match ($mode) {
            'material' => fn (User $user) => Material::whereKey($id)->visibleTo($user)->exists(),
            'announcement' => fn (User $user) => Announcement::whereKey($id)->visibleTo($user)->exists(),
            'live' => function (User $user) use ($id) {
                $schedule = LiveStream::find($id)?->schedule;

                return $schedule && $this->live->reaches($schedule, $user);
            },
            default => fn () => true,
        };
    }
}
