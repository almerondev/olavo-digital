<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider', 40);
            $table->string('provider_input_id');
            $table->string('ingest_rtmps_url', 500)->nullable();
            $table->string('ingest_srt_url', 500)->nullable();
            $table->text('stream_key');
            $table->string('playback_ref', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['provider', 'provider_input_id']);
        });

        Schema::create('live_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_channel_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cover_upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default('scheduled');
            $table->boolean('is_adhoc')->default(false);
            $table->boolean('notify_on_start')->default(true);
            $table->timestamps();
            $table->index(['status', 'starts_at']);
            $table->index(['teacher_id', 'starts_at']);
            $table->index(['live_channel_id', 'status', 'starts_at']);
        });

        DB::statement('ALTER TABLE live_schedules ADD CONSTRAINT live_schedules_period_check CHECK (ends_at > starts_at)');

        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('live_channel_id')->constrained()->restrictOnDelete();
            $table->string('provider_session_ref');
            $table->timestamp('started_at');
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('peak_viewers')->nullable();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['live_channel_id', 'provider_session_ref']);
            $table->index(['live_channel_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_streams');
        Schema::dropIfExists('live_schedules');
        Schema::dropIfExists('live_channels');
    }
};
