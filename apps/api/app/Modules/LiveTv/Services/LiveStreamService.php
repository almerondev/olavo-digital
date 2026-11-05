<?php

namespace App\Modules\LiveTv\Services;

use App\Modules\Auth\Models\User;
use App\Modules\LiveTv\Enums\ScheduleStatus;
use App\Modules\LiveTv\Events\LiveStreamStarted;
use App\Modules\LiveTv\Models\LiveSchedule;
use App\Modules\LiveTv\Models\LiveStream;
use App\Modules\Materials\Enums\MaterialStatus;
use App\Modules\Materials\Enums\MaterialType;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Models\VideoAsset;
use App\Support\Audit\Audit;
use App\Support\Http\ApiException;
use App\Support\YouTubeUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Inicia e encerra pelo painel: o vídeo vem do YouTube, e aqui fica o controle de quem assiste. */
class LiveStreamService
{
    public function start(LiveSchedule $schedule, User $actor): LiveStream
    {
        if (! YouTubeUrl::id($schedule->youtube_url)) {
            throw new ApiException('Cole o link da live do YouTube antes de iniciar.', 'youtube_url_required', 422);
        }

        $stream = DB::transaction(function () use ($schedule) {
            if (LiveStream::open()->lockForUpdate()->exists()) {
                throw new ApiException('Já existe uma transmissão no ar. Encerre-a antes de iniciar outra.', 'stream_on_air', 409);
            }

            $schedule->update(['status' => ScheduleStatus::Live]);

            return LiveStream::create(['live_schedule_id' => $schedule->id, 'started_at' => now()]);
        });

        Audit::record('live.started', null, ['stream_id' => $stream->id], $actor);
        LiveStreamStarted::dispatch($stream);

        return $stream;
    }

    /** O link da live continua válido no YouTube depois do fim, então ele já é a gravação. */
    public function end(LiveStream $stream, User $actor): void
    {
        if ($stream->ended_at) {
            throw new ApiException('Esta transmissão já terminou.', 'stream_ended', 409);
        }

        DB::transaction(function () use ($stream) {
            $schedule = $stream->schedule;

            $stream->update(['ended_at' => now(), 'material_id' => $this->recordingDraft($schedule)->id]);
            $schedule->update(['status' => ScheduleStatus::Ended]);
        });

        Audit::record('live.ended', null, ['stream_id' => $stream->id], $actor);
    }

    private function recordingDraft(LiveSchedule $schedule): Material
    {
        $material = Material::create([
            'slug' => $this->uniqueSlug($schedule->title),
            'title' => $schedule->title,
            'description' => $schedule->description,
            'type' => MaterialType::ExternalVideo,
            'status' => MaterialStatus::Draft,
            'teacher_id' => $schedule->teacher_id,
            'cover_upload_id' => $schedule->cover_upload_id,
            'external_url' => $schedule->youtube_url,
            'video_asset_id' => VideoAsset::firstOrCreate(['provider' => 'youtube', 'provider_ref' => $schedule->youtube_url])->id,
        ]);

        if ($schedule->category_id) {
            $material->categories()->attach($schedule->category_id, ['is_primary' => true]);
        }

        return $material;
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
