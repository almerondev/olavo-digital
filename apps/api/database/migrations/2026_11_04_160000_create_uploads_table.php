<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('disk', 40);
            $table->string('object_key', 500);
            $table->string('purpose', 20);
            $table->string('file_class', 20);
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('avatar_upload_id')->references('id')->on('uploads')->nullOnDelete();
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->foreign('photo_upload_id')->references('id')->on('uploads')->nullOnDelete();
            $table->foreign('banner_upload_id')->references('id')->on('uploads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropForeign(['photo_upload_id']);
            $table->dropForeign(['banner_upload_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['avatar_upload_id']);
        });

        Schema::dropIfExists('uploads');
    }
};
