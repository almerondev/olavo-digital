<?php

namespace App\Modules\Communication\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Communication\Http\Resources\AnnouncementResource;
use App\Modules\Communication\Models\Announcement;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementBoard
{
    private const HOME_LIMIT = 12;

    public function __construct(private NotificationCenter $center)
    {
    }

    public function forUser(User $user): Builder
    {
        return Announcement::visibleTo($user)
            ->with(['image', 'reads' => fn ($reads) => $reads->where('user_id', $user->id)])
            ->orderByDesc('is_pinned')
            ->orderByDesc('priority')
            ->orderByDesc('published_at');
    }

    /** Fixado continua na home depois de lido; o restante sai quando o aluno dispensa. */
    public function forHome(User $user): array
    {
        $items = $this->forUser($user)
            ->where(fn (Builder $query) => $query
                ->where('is_pinned', true)
                ->orWhereDoesntHave('reads', fn (Builder $reads) => $reads->where('user_id', $user->id)))
            ->limit(self::HOME_LIMIT)
            ->get();

        return AnnouncementResource::collection($items)->resolve();
    }

    /** Avisos agendados só notificam quando entram no ar. */
    public function notifyDue(): int
    {
        $due = Announcement::onAir()->where('send_notification', true)->whereNull('notified_at')->get();

        foreach ($due as $announcement) {
            $announcement->update(['notified_at' => now()]);
            $this->center->sendOnce(
                $announcement,
                'announcement',
                $announcement->title,
                strip_tags($announcement->body),
                '/avisos',
                ['mode' => 'announcement', 'ids' => [$announcement->id]],
                $announcement->image_upload_id,
            );
        }

        return $due->count();
    }
}
