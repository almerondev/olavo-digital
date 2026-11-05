<?php

namespace App\Modules\LiveTv\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\LiveTv\Enums\ScheduleStatus;
use App\Modules\LiveTv\Http\Requests\SaveScheduleRequest;
use App\Modules\LiveTv\Http\Resources\ScheduleResource;
use App\Modules\LiveTv\Models\LiveChannel;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\LiveTv\Providers\LiveEvent;
use App\Modules\LiveTv\Providers\LiveProvider;
use App\Modules\LiveTv\Services\LiveStreamService;
use App\Support\Audit\Audit;
use App\Support\Http\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LiveAdminController extends Controller
{
    private const SCHEDULE_RELATIONS = ['teacher', 'category', 'cover', 'audienceTargets'];

    public function __construct(private LiveProvider $provider, private LiveStreamService $streams)
    {
    }

    public function channels(): JsonResponse
    {
        return response()->json([
            'data' => LiveChannel::orderBy('name')->get()->map(fn (LiveChannel $channel) => $this->presentChannel($channel)),
            'meta' => ['simulated' => $this->provider->name() === 'simulated'],
        ]);
    }

    public function storeChannel(Request $request): JsonResponse
    {
        $name = $request->validate(['name' => ['required', 'string', 'max:255']], [], ['name' => 'nome'])['name'];

        $channel = LiveChannel::create(['name' => $name, 'provider' => $this->provider->name()] + $this->provider->createInput($name));
        Audit::record('live.channel_created', null, ['channel_id' => $channel->id]);

        return response()->json(['data' => $this->presentChannel($channel)], 201);
    }

    /** A chave só sai por esta rota, que exige permissão própria e fica auditada. */
    public function channelKey(LiveChannel $channel): JsonResponse
    {
        Audit::record('live.key_viewed', null, ['channel_id' => $channel->id]);

        return response()->json(['data' => ['stream_key' => $channel->stream_key]]);
    }

    public function rotateKey(LiveChannel $channel): JsonResponse
    {
        if (LiveStream::where('live_channel_id', $channel->id)->open()->exists()) {
            throw new ApiException('Encerre a transmissão antes de trocar a chave.', 'channel_on_air', 409);
        }

        $channel->update(['stream_key' => $this->provider->rotateKey($channel)]);
        Audit::record('live.key_rotated', null, ['channel_id' => $channel->id]);

        return response()->json(['data' => ['stream_key' => $channel->stream_key]]);
    }

    public function schedules(Request $request): JsonResponse
    {
        return response()->json(['data' => ScheduleResource::collection(
            LiveSchedule::with(self::SCHEDULE_RELATIONS)->where('starts_at', '>=', now()->subDays(30))->orderByDesc('starts_at')->limit(100)->get()
        )]);
    }

    public function storeSchedule(SaveScheduleRequest $request): JsonResponse
    {
        $schedule = $this->persist(new LiveSchedule, $request);

        return (new ScheduleResource($schedule))->response()->setStatusCode(201);
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

        return new ScheduleResource($schedule->load(self::SCHEDULE_RELATIONS));
    }

    public function streams(): JsonResponse
    {
        $streams = LiveStream::with('schedule', 'channel', 'material')->latest('started_at')->limit(50)->get();

        return response()->json(['data' => $streams->map(fn (LiveStream $stream) => [
            'id' => $stream->id,
            'title' => $stream->schedule->title,
            'channel' => $stream->channel->name,
            'started_at' => $stream->started_at->toIso8601String(),
            'ended_at' => $stream->ended_at?->toIso8601String(),
            'reconnecting' => $stream->disconnected_at !== null && $stream->ended_at === null,
            'session' => $stream->provider_session_ref,
            'material' => $stream->material ? ['id' => $stream->material->id, 'status' => $stream->material->status->value] : null,
        ])]);
    }

    public function endStream(LiveStream $stream): Response
    {
        if ($stream->ended_at) {
            throw new ApiException('Esta transmissão já terminou.', 'stream_ended', 409);
        }

        $this->streams->endNow($stream);

        return response()->noContent();
    }

    /** Só existe com o provedor simulado: emite o mesmo evento que o encoder geraria. */
    public function simulate(Request $request, LiveChannel $channel): Response
    {
        abort_unless($this->provider->name() === 'simulated', 404);

        $data = $request->validate([
            'type' => ['required', 'in:'.LiveEvent::CONNECTED.','.LiveEvent::DISCONNECTED.','.LiveEvent::RECORDING_READY],
            'session' => ['nullable', 'string'],
        ]);

        $session = $data['session'] ?? (string) Str::uuid();
        $body = json_encode(['type' => $data['type'], 'input_id' => $channel->provider_input_id, 'session' => $session]);
        $signed = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_LIVE_SIGNATURE' => $this->provider->sign($body)], $body);

        $this->streams->handle($this->provider->parseWebhook($signed));

        return response()->noContent();
    }

    private function persist(LiveSchedule $schedule, SaveScheduleRequest $request): LiveSchedule
    {
        return DB::transaction(function () use ($schedule, $request) {
            $schedule->fill($request->safe()->except('group_ids'))->save();
            $schedule->syncAudience($request->validated('group_ids', []));
            Audit::record('live.schedule_saved', null, ['schedule_id' => $schedule->id]);

            return $schedule->refresh()->load(self::SCHEDULE_RELATIONS);
        });
    }

    private function assertScheduled(LiveSchedule $schedule): void
    {
        if ($schedule->status !== ScheduleStatus::Scheduled) {
            throw new ApiException('Só transmissões agendadas podem ser alteradas.', 'invalid_transition', 409);
        }
    }

    /** @return array<string, mixed> */
    private function presentChannel(LiveChannel $channel): array
    {
        return [
            'id' => $channel->id,
            'name' => $channel->name,
            'provider' => $channel->provider,
            'ingest_rtmps_url' => $channel->ingest_rtmps_url,
            'ingest_srt_url' => $channel->ingest_srt_url,
            'on_air' => $channel->streams()->open()->exists(),
        ];
    }
}
