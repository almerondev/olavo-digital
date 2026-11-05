<?php

namespace App\Modules\LiveTv\Services;

use App\Modules\LiveTv\Enums\ScheduleStatus;
use App\Modules\LiveTv\Events\LiveStreamStarted;
use App\Modules\LiveTv\Jobs\EndDisconnectedStream;
use App\Modules\LiveTv\Models\LiveChannel;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\LiveTv\Providers\LiveEvent;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Models\MaterialFile;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Aplica os eventos do provedor. Eles podem chegar repetidos ou fora de ordem. */
class LiveStreamService
{
    /** Tolerância para o encoder voltar sem encerrar a aula nem notificar de novo. */
    private const GRACE_SECONDS = 180;

    private const MATCH_MINUTES_BEFORE = 30;
    private const MATCH_MINUTES_AFTER = 60;

    public function handle(LiveEvent $event): void
    {
        $channel = LiveChannel::where('provider_input_id', $event->inputId)->first();

        if (! $channel) {
            return;
        }

        match ($event->type) {
            LiveEvent::CONNECTED => $this->connected($channel, $event),
            LiveEvent::DISCONNECTED => $this->disconnected($channel),
            LiveEvent::RECORDING_READY => $this->recordingReady($channel, $event),
        };
    }

    public function endIfStillDisconnected(int $streamId, string $disconnectedAt): void
    {
        DB::transaction(function () use ($streamId, $disconnectedAt) {
            $stream = LiveStream::lockForUpdate()->find($streamId);

            // Voltou no prazo, ou já caiu outra vez e existe outro agendamento para esta queda.
            if (! $stream || $stream->ended_at || $stream->disconnected_at?->toIso8601String() !== $disconnectedAt) {
                return;
            }

            $this->finish($stream);
        });
    }

    /** Encerramento pelo painel, quando o encoder não avisa. */
    public function endNow(LiveStream $stream): void
    {
        DB::transaction(fn () => $this->finish($stream));
    }

    private function finish(LiveStream $stream): void
    {
        $stream->update(['ended_at' => $stream->disconnected_at ?? now()]);
        $stream->schedule->update(['status' => ScheduleStatus::Ended]);
        Audit::record('live.ended', $stream);
    }

    private function connected(LiveChannel $channel, LiveEvent $event): void
    {
        $started = DB::transaction(function () use ($channel, $event) {
            $open = LiveStream::where('live_channel_id', $channel->id)->open()->lockForUpdate()->first();

            if ($open) {
                $open->update(['disconnected_at' => null]);

                return null;
            }

            if (LiveStream::where('live_channel_id', $channel->id)->where('provider_session_ref', $event->sessionRef)->exists()) {
                return null;
            }

            $schedule = $this->matchSchedule($channel);
            $schedule->update(['status' => ScheduleStatus::Live]);

            return LiveStream::create([
                'live_schedule_id' => $schedule->id,
                'live_channel_id' => $channel->id,
                'provider_session_ref' => $event->sessionRef,
                'started_at' => now(),
            ]);
        });

        if ($started) {
            Audit::record('live.started', $started);
            LiveStreamStarted::dispatch($started);
        }
    }

    private function disconnected(LiveChannel $channel): void
    {
        $stream = LiveStream::where('live_channel_id', $channel->id)->open()->first();

        if (! $stream || $stream->disconnected_at) {
            return;
        }

        $at = now()->startOfSecond();
        $stream->update(['disconnected_at' => $at]);

        EndDisconnectedStream::dispatch($stream->id, $at->toIso8601String())->delay(now()->addSeconds(self::GRACE_SECONDS));
    }

    private function matchSchedule(LiveChannel $channel): LiveSchedule
    {
        $planned = LiveSchedule::query()
            ->where('live_channel_id', $channel->id)
            ->where('status', ScheduleStatus::Scheduled)
            ->whereBetween('starts_at', [now()->subMinutes(self::MATCH_MINUTES_AFTER), now()->addMinutes(self::MATCH_MINUTES_BEFORE)])
            ->orderByRaw('abs(timestampdiff(second, starts_at, ?))', [now()->toDateTimeString()])
            ->first();

        return $planned ?? LiveSchedule::create([
            'live_channel_id' => $channel->id,
            'title' => 'Transmissão ao vivo',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'status' => ScheduleStatus::Scheduled,
            'is_adhoc' => true,
            'notify_on_start' => false,
        ]);
    }

    /** A gravação vira rascunho: a equipe completa capa e descrição antes de publicar. */
    private function recordingReady(LiveChannel $channel, LiveEvent $event): void
    {
        $stream = LiveStream::with('schedule')
            ->where('live_channel_id', $channel->id)
            ->where('provider_session_ref', $event->sessionRef)
            ->first();

        if (! $stream || $stream->material_id || ! $event->recordingUploadId) {
            return;
        }

        DB::transaction(function () use ($stream, $event) {
            $schedule = $stream->schedule;

            $material = Material::create([
                'slug' => $this->uniqueSlug($schedule->title),
                'title' => $schedule->title,
                'description' => $schedule->description,
                'type' => MaterialType::Video,
                'status' => MaterialStatus::Draft,
                'teacher_id' => $schedule->teacher_id,
                'cover_upload_id' => $schedule->cover_upload_id,
            ]);

            $material->files()->create(['upload_id' => $event->recordingUploadId, 'role' => MaterialFile::ROLE_PRIMARY]);

            if ($schedule->category_id) {
                $material->categories()->attach($schedule->category_id, ['is_primary' => true]);
            }

            $stream->update(['material_id' => $material->id]);
        });
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 170, '').'-gravacao';
        $slug = $base;

        for ($i = 2; Material::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
