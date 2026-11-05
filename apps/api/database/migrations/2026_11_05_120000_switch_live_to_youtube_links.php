<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A transmissão passa a vir de um link do YouTube: canais e chaves de encoder deixam de existir. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropForeign(['live_channel_id']);
            $table->dropUnique(['live_channel_id', 'provider_session_ref']);
            $table->dropIndex(['live_channel_id', 'ended_at']);
            $table->dropColumn(['live_channel_id', 'provider_session_ref', 'disconnected_at', 'peak_viewers']);
            $table->index('ended_at');
        });

        Schema::table('live_schedules', function (Blueprint $table) {
            $table->dropForeign(['live_channel_id']);
            $table->dropIndex(['live_channel_id', 'status', 'starts_at']);
            $table->dropColumn('live_channel_id');
            $table->string('youtube_url', 500)->nullable()->after('description');
            $table->foreignId('course_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
        });

        Schema::dropIfExists('live_channels');
    }

    public function down(): void
    {
        Schema::create('live_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
};
