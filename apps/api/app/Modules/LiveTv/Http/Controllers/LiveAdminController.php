<?php

namespace App\Modules\LiveTv\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\LiveTv\Enums\ScheduleStatus;
use App\Modules\LiveTv\Http\Requests\SaveScheduleRequest;
use App\Modules\LiveTv\Http\Resources\ScheduleResource;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\LiveTv\Services\LiveDirectory;
use App\Modules\LiveTv\Services\LiveStreamService;
use App\Modules\Settings\Services\SettingsService;
use App\Support\Audit\Audit;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LiveAdminController extends Controller
{
    private const RELATIONS = ['teacher', 'category', 'course', 'cover', 'audienceTargets'];

    public function __construct(
        private LiveStreamService $streams,
        private LiveDirectory $directory,
        private SettingsService $settings,
    ) {
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => ['schedule_enabled' => $this->directory->scheduleEnabled()]]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $enabled = $request->validate(['schedule_enabled' => ['required', 'boolean']])['schedule_enabled'];

        $this->settings->set(LiveDirectory::SCHEDULE_SETTING, $enabled);
        Audit::record('live.schedule_toggled', null, ['enabled' => $enabled]);

        return response()->json(['data' => ['schedule_enabled' => $enabled]]);
    }

    public function schedules(): JsonResponse
    {
        return response()->json(['data' => ScheduleResource::collection(
            LiveSchedule::with(self::RELATIONS)->where('is_adhoc', false)->where('starts_at', '>=', now()->subDays(30))->orderByDesc('starts_at')->limit(100)->get()
        )]);
    }

    public function storeSchedule(SaveScheduleRequest $request): JsonResponse
    {
        return (new ScheduleResource($this->persist(new LiveSchedule, $request)))->response()->setStatusCode(201);
    }

    public function updateSchedule(SaveScheduleRequest $request, LiveSchedule $schedule): ScheduleResource
    {
        $this->assertScheduled($schedule);

        return new ScheduleResource($this->persist($schedule, $request));
    }

    public function cancelSchedule(LiveSchedule $schedule): ScheduleResource
    {
        $this->assertScheduled($schedule);

        $schedule->update(['status' => ScheduleStatus::Cancelled]);
        Audit::record('live.schedule_cancelled', null, ['schedule_id' => $schedule->id]);

        return new ScheduleResource($schedule->load(self::RELATIONS));
    }

    /** Entrar ao vivo direto: cola o link, dá um título e a transmissão começa. */
    public function goLive(SaveScheduleRequest $request): JsonResponse
    {
        $schedule = $this->persist(new LiveSchedule(['is_adhoc' => true]), $request);
        $stream = $this->streams->start($schedule, $request->user());

        return response()->json(['data' => ['stream_id' => $stream->id]], 201);
    }

    public function startSchedule(Request $request, LiveSchedule $schedule): JsonResponse
    {
        $this->assertScheduled($schedule);

        return response()->json(['data' => ['stream_id' => $this->streams->start($schedule, $request->user())->id]]);
    }

    public function streams(): JsonResponse
    {
        $streams = LiveStream::with('schedule.course', 'material')->latest('started_at')->limit(50)->get();

        return response()->json(['data' => $streams->map(fn (LiveStream $stream) => [
            'id' => $stream->id,
            'title' => $stream->schedule->title,
            'course' => $stream->schedule->course?->name,
            'youtube_url' => $stream->schedule->youtube_url,
            'started_at' => $stream->started_at->toIso8601String(),
            'ended_at' => $stream->ended_at?->toIso8601String(),
            'material' => $stream->material ? ['id' => $stream->material->id, 'status' => $stream->material->status->value] : null,
        ])]);
    }

    public function endStream(Request $request, LiveStream $stream): Response
    {
        $this->streams->end($stream, $request->user());

        return response()->noContent();
    }

    private function persist(LiveSchedule $schedule, SaveScheduleRequest $request): LiveSchedule
    {
        return DB::transaction(function () use ($schedule, $request) {
            $schedule->fill($request->safe()->except('group_ids'))->save();
            $schedule->syncAudience($request->validated('group_ids', []));
            Audit::record('live.schedule_saved', null, ['schedule_id' => $schedule->id]);

            return $schedule->refresh()->load(self::RELATIONS);
        });
    }

    private function assertScheduled(LiveSchedule $schedule): void
    {
        if ($schedule->status !== ScheduleStatus::Scheduled) {
            throw new ApiException('Só transmissões agendadas podem ser alteradas.', 'invalid_transition', 409);
        }
    }
}
