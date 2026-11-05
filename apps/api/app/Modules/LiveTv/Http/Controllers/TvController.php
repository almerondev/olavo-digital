<?php

namespace App\Modules\LiveTv\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\LiveTv\Enums\ScheduleStatus;
use App\Modules\LiveTv\Http\Resources\ScheduleResource;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\LiveTv\Providers\LiveProvider;
use App\Modules\LiveTv\Services\LiveDirectory;
use App\Modules\Materials\Http\Resources\MaterialCardResource;
use App\Modules\Materials\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TvController extends Controller
{
    private const RELATIONS = ['teacher', 'category', 'cover', 'audienceTargets'];
    private const SCHEDULE_DAYS = 14;

    public function __construct(private LiveDirectory $directory, private LiveProvider $provider)
    {
    }

    public function now(Request $request): JsonResponse
    {
        $user = $request->user();
        $stream = $this->directory->currentFor($user);

        $upcoming = LiveSchedule::with(self::RELATIONS)
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
            'live' => $stream ? [
                'stream_id' => $stream->id,
                'started_at' => $stream->started_at->toIso8601String(),
                'reconnecting' => $stream->disconnected_at !== null,
                'schedule' => new ScheduleResource($stream->schedule),
            ] : null,
            'upcoming' => ScheduleResource::collection($upcoming),
            'recordings' => MaterialCardResource::collection($recordings),
        ]]);
    }

    public function schedule(Request $request): JsonResponse
    {
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

    public function playback(Request $request, LiveStream $stream): JsonResponse
    {
        abort_if($stream->ended_at !== null, 404);
        abort_unless($this->directory->reaches($stream->schedule, $request->user()), 404);

        return response()->json(['data' => $this->provider->playback($stream->channel)]);
    }
}
