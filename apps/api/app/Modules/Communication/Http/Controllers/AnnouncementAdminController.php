<?php

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Http\Requests\SaveAnnouncementRequest;
use App\Modules\Communication\Models\Announcement;
use App\Modules\Communication\Services\AnnouncementBoard;
use App\Modules\Uploads\Http\Resources\UploadResource;
use App\Support\Audit\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class AnnouncementAdminController extends Controller
{
    public function __construct(private AnnouncementBoard $board)
    {
    }

    public function index(): JsonResponse
    {
        $items = Announcement::with('image', 'course', 'audienceTargets')
            ->withCount('reads')
            ->latest('published_at')
            ->limit(200)
            ->get();

        return response()->json(['data' => $items->map(fn (Announcement $item) => $this->present($item))]);
    }

    public function store(SaveAnnouncementRequest $request): JsonResponse
    {
        $announcement = Announcement::create([...Arr::except($request->validated(), 'group_ids'), 'created_by' => $request->user()->id]);
        $this->afterSave($announcement, $request);
        Audit::record('announcement.created', $announcement);

        return response()->json(['data' => $this->present($announcement->load('image', 'course', 'audienceTargets')->loadCount('reads'))], 201);
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $announcement->update(Arr::except($request->validated(), 'group_ids'));
        $this->afterSave($announcement, $request);
        Audit::record('announcement.updated', $announcement);

        return response()->json(['data' => $this->present($announcement->load('image', 'course', 'audienceTargets')->loadCount('reads'))]);
    }

    public function destroy(Announcement $announcement): Response
    {
        $announcement->delete();
        Audit::record('announcement.deleted', $announcement);

        return response()->noContent();
    }

    /** Quem já confirmou a leitura. */
    public function reads(Announcement $announcement): JsonResponse
    {
        $reads = $announcement->reads()->with('user:id,name')->latest('read_at')->limit(500)->get();

        return response()->json(['data' => $reads->map(fn ($read) => [
            'name' => $read->user?->name,
            'read_at' => $read->read_at->toIso8601String(),
        ])]);
    }

    private function afterSave(Announcement $announcement, Request $request): void
    {
        $announcement->syncAudience($request->validated('group_ids'));
        $this->board->notifyDue();
    }

    private function present(Announcement $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'body' => $item->body,
            'image_upload_id' => $item->image_upload_id,
            'image' => $item->image ? new UploadResource($item->image) : null,
            'display_mode' => $item->display_mode,
            'priority' => $item->priority,
            'course' => $item->course ? ['id' => $item->course->id, 'name' => $item->course->name] : null,
            'published_at' => $item->published_at->toIso8601String(),
            'expires_at' => $item->expires_at?->toIso8601String(),
            'is_pinned' => $item->is_pinned,
            'requires_ack' => $item->requires_ack,
            'send_notification' => $item->send_notification,
            'notified_at' => $item->notified_at?->toIso8601String(),
            'reads_count' => $item->reads_count,
            'group_ids' => $item->audienceTargets->pluck('student_group_id')->filter()->values(),
        ];
    }
}
