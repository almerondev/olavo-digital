<?php

namespace App\Modules\Playback\Services;

use App\Modules\Access\Models\Student;
use App\Modules\Auth\Models\User;
use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Models\MaterialFile;
use App\Modules\Playback\Models\WatchHistory;
use App\Modules\Playback\Models\WatchProgress;
use App\Modules\Uploads\Services\UploadStorage;
use App\Support\Http\ApiException;
use Illuminate\Database\Eloquent\Builder;

class PlaybackService
{
    /** A partir daqui o vídeo conta como concluído, mesmo sem assistir aos créditos. */
    private const COMPLETED_RATIO = 0.95;

    /** Acessos com intervalo menor formam uma única entrada no histórico. */
    private const SESSION_GAP_MINUTES = 30;

    /** Limita o tempo somado por evento: um relógio adulterado não infla as métricas. */
    private const MAX_SECONDS_PER_EVENT = 60;

    public function __construct(private UploadStorage $storage)
    {
    }

    /** @return array<string, mixed> */
    public function source(Material $material, User $user): array
    {
        $material->loadMissing('primaryFile.upload', 'files.upload', 'categories');
        $upload = $material->primaryFile?->upload;

        if ($material->type !== MaterialType::Video || ! $upload?->isReady()) {
            throw new ApiException('Este conteúdo não tem vídeo disponível.', 'video_unavailable', 404);
        }

        $progress = $user->student
            ? WatchProgress::where('student_id', $user->student->id)->where('material_id', $material->id)->first()
            : null;

        return [
            'kind' => $upload->file_class === 'video' ? 'file' : 'hls',
            'url' => $this->storage->temporaryUrl($upload),
            'subtitles' => $material->files
                ->where('role', MaterialFile::ROLE_SUBTITLE)
                ->filter(fn (MaterialFile $file) => $file->upload->isReady())
                ->map(fn (MaterialFile $file) => [
                    'locale' => $file->locale ?? 'pt-BR',
                    'label' => 'Português',
                    'url' => $this->storage->temporaryUrl($file->upload),
                ])->values(),
            'resume_at_seconds' => $this->resumePoint($progress),
            'completed' => $progress?->completed_at !== null,
            'next' => $this->next($material, $user),
        ];
    }

    public function saveProgress(Student $student, Material $material, int $position, ?int $duration, int $watched): void
    {
        $now = now();
        $position = $duration ? min($position, $duration) : $position;

        WatchProgress::upsert([[
            'student_id' => $student->id,
            'material_id' => $material->id,
            'position_seconds' => $position,
            'duration_seconds' => $duration,
            'last_watched_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['student_id', 'material_id'], ['position_seconds', 'duration_seconds', 'last_watched_at', 'updated_at']);

        if ($duration && $position >= $duration * self::COMPLETED_RATIO) {
            $this->markCompleted($student, $material);
        }

        $this->touchHistory($student, $material, $watched);
    }

    public function complete(Student $student, Material $material): void
    {
        $this->saveProgress($student, $material, 0, null, 0);
        $this->markCompleted($student, $material);
    }

    public function touchHistory(Student $student, Material $material, int $watched = 0): void
    {
        $watched = min(max(0, $watched), self::MAX_SECONDS_PER_EVENT);

        $session = WatchHistory::query()
            ->where('student_id', $student->id)
            ->where('material_id', $material->id)
            ->whereNull('hidden_at')
            ->where('last_event_at', '>=', now()->subMinutes(self::SESSION_GAP_MINUTES))
            ->latest('id')
            ->first();

        if ($session) {
            $session->update(['last_event_at' => now(), 'seconds_watched' => $session->seconds_watched + $watched]);

            return;
        }

        WatchHistory::create([
            'student_id' => $student->id,
            'material_id' => $material->id,
            'started_at' => now(),
            'last_event_at' => now(),
            'seconds_watched' => $watched,
        ]);
    }

    private function markCompleted(Student $student, Material $material): void
    {
        WatchProgress::where('student_id', $student->id)
            ->where('material_id', $material->id)
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]);
    }

    private function resumePoint(?WatchProgress $progress): int
    {
        return $progress && ! $progress->completed_at ? $progress->position_seconds : 0;
    }

    /** @return array{slug: string, title: string}|null */
    private function next(Material $material, User $user): ?array
    {
        $categories = $material->categories->modelKeys();

        $next = Material::visibleTo($user)
            ->whereKeyNot($material->id)
            ->where('type', MaterialType::Video)
            ->whereHas('categories', fn (Builder $query) => $query->whereIn('categories.id', $categories))
            ->when($user->student, fn (Builder $query, Student $student) => $query->whereNotIn(
                'materials.id',
                WatchProgress::where('student_id', $student->id)->whereNotNull('completed_at')->select('material_id'),
            ))
            ->orderByDesc('published_at')
            ->first(['id', 'slug', 'title']);

        return $next ? ['slug' => $next->slug, 'title' => $next->title] : null;
    }
}
