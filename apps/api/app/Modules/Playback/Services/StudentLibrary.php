<?php

namespace App\Modules\Playback\Services;

use App\Modules\Access\Models\Student;
use App\Modules\Auth\Models\User;
use App\Support\Http\ApiException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Dados do aluno usados nos cards. Carrega uma vez por requisição, evitando uma consulta por card. */
class StudentLibrary
{
    private ?User $user = null;
    private ?array $favorites = null;
    private ?Collection $progress = null;

    public function forUser(?User $user): self
    {
        if ($user?->id !== $this->user?->id) {
            $this->favorites = null;
            $this->progress = null;
        }

        $this->user = $user;

        return $this;
    }

    public function requireStudent(User $user): Student
    {
        return $user->student ?? throw new ApiException('Esta função é exclusiva para alunos.', 'student_only', 403);
    }

    public function isFavorite(int $materialId): bool
    {
        return isset($this->favoriteIds()[$materialId]);
    }

    /** @return array{percent: int, remaining_seconds: int|null, completed: bool}|null */
    public function progressOf(int $materialId): ?array
    {
        $row = $this->progressRows()->get($materialId);

        if (! $row) {
            return null;
        }

        $duration = (int) $row->duration_seconds;
        $completed = $row->completed_at !== null;

        return [
            'percent' => $completed ? 100 : ($duration > 0 ? min(99, (int) round($row->position_seconds / $duration * 100)) : 0),
            'remaining_seconds' => $duration > 0 && ! $completed ? max(0, $duration - $row->position_seconds) : null,
            'completed' => $completed,
        ];
    }

    /** @return array<int, true> */
    private function favoriteIds(): array
    {
        $studentId = $this->user?->student?->id;

        return $this->favorites ??= $studentId
            ? DB::table('favorites')->where('student_id', $studentId)->pluck('material_id')->flip()->map(fn () => true)->all()
            : [];
    }

    private function progressRows(): Collection
    {
        $studentId = $this->user?->student?->id;

        return $this->progress ??= $studentId
            ? DB::table('watch_progress')->where('student_id', $studentId)->get()->keyBy('material_id')
            : collect();
    }
}
