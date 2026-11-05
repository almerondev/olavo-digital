<?php

namespace App\Modules\LiveTv\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\LiveTv\Enums\ScheduleStatus;
use App\Modules\LiveTv\Http\Resources\ScheduleResource;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\LiveTv\Services\LiveDirectory;
use App\Modules\Materials\Http\Resources\MaterialCardResource;
use App\Modules\Materials\Models\Material;
use App\Support\YouTubeUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TvController extends Controller
{
    private const RELATIONS = ['teacher', 'category', 'course', 'cover', 'audienceTargets'];
    private const SCHEDULE_DAYS = 14;

    public function __construct(private LiveDirectory $directory)
    {
    }

    public function now(Request $request): JsonResponse
    {
        $user = $request->user();
        $stream = $this->directory->currentFor($user);
        $enabled = $this->directory->scheduleEnabled();

        $upcoming = ! $enabled ? collect() : LiveSchedule::with(self::RELATIONS)
            ->where('status', ScheduleStatus::Scheduled)
            ->where('starts_at', '>=', now()->subHour())
            ->orderBy('starts_at')
            ->limit(30)
            ->get()
            ->filter(fn (LiveSchedule $schedule) => $this->directory->reaches($schedule, $user))
            ->take(8)
            ->values();

        $recordings = Material::visibleTo($user)
            ->with('teacher', 'cover', 'banner', 'categories')
            ->whereIn('materials.id', LiveStream::whereNotNull('material_id')->select('material_id'))
            ->orderByDesc('published_at')
            ->limit(12)
            ->get();

        return response()->json(['data' => [
            'schedule_enabled' => $enabled,
            'live' => $stream ? [
                'stream_id' => $stream->id,
                'started_at' => $stream->started_at->toIso8601String(),
                'embed_url' => YouTubeUrl::embed($stream->schedule->youtube_url, ['autoplay' => 1, 'mute' => 1, 'rel' => 0]),
                'schedule' => new ScheduleResource($stream->schedule),
            ] : null,
            'upcoming' => ScheduleResource::collection($upcoming),
            'recordings' => MaterialCardResource::collection($recordings),
        ]]);
    }

    public function schedule(Request $request): JsonResponse
    {
        if (! $this->directory->scheduleEnabled()) {
            return response()->json(['data' => []]);
        }

        $user = $request->user();

        $items = LiveSchedule::with(self::RELATIONS)
            ->whereBetween('starts_at', [now()->startOfDay(), now()->addDays(self::SCHEDULE_DAYS)->endOfDay()])
            ->where('is_adhoc', false)
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (LiveSchedule $schedule) => $this->directory->reaches($schedule, $user))
            ->values();

        return response()->json(['data' => ScheduleResource::collection($items)]);
    }
}
