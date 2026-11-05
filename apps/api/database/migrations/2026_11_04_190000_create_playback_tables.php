<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['student_id', 'material_id']);
            $table->index(['student_id', 'created_at']);
        });

        Schema::create('watch_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position_seconds')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_watched_at')->useCurrent();
            $table->timestamps();
            $table->unique(['student_id', 'material_id']);
            $table->index(['student_id', 'completed_at', 'last_watched_at']);
        });

        Schema::create('watch_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_event_at')->useCurrent();
            $table->unsignedInteger('seconds_watched')->default(0);
            $table->timestamp('hidden_at')->nullable();
            $table->index(['student_id', 'hidden_at', 'started_at']);
            $table->index(['material_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_history');
        Schema::dropIfExists('watch_progress');
        Schema::dropIfExists('favorites');
    }
};
