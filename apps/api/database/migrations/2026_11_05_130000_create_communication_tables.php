<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('body');
            $table->foreignId('image_upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->string('display_mode', 20)->default('card');
            $table->unsignedTinyInteger('priority')->default(0);
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('published_at');
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('requires_ack')->default(false);
            $table->boolean('send_notification')->default(false);
            $table->dateTime('notified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['published_at', 'expires_at']);
            $table->index(['send_notification', 'notified_at']);
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('read_at');

            $table->unique(['announcement_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('title', 160);
            $table->string('body', 500);
            $table->string('url', 500)->nullable();
            $table->foreignId('image_upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('audience');
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id']);
            $table->index('created_at');
        });

        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('created_at')->nullable();

            $table->unique(['notification_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('public_key', 255);
            $table->string('auth_token', 255);
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
    }
};
