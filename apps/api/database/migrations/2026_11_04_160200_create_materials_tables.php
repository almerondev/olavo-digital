<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_assets', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('provider_ref', 500);
            $table->string('status', 20)->default('ready');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_ref'], 'video_assets_provider_unique');
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 200)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 30);
            $table->string('status', 20)->default('draft');
            $table->string('visibility', 20)->default('all');
            $table->foreignId('teacher_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cover_upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->foreignId('video_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_url', 1000)->nullable();
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->boolean('allow_download')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
            $table->index(['status', 'publish_at']);
            $table->index(['teacher_id', 'status']);
            $table->index(['type', 'status', 'published_at']);
            $table->index(['is_featured', 'status']);
        });

        Schema::create('material_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('upload_id')->constrained()->restrictOnDelete();
            $table->string('role', 20)->default('primary');
            $table->string('locale', 10)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['material_id', 'upload_id']);
            $table->index(['material_id', 'role']);
        });

        Schema::create('material_categories', function (Blueprint $table) {
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            // Índice único só atinge a linha principal: garante uma matéria principal por material.
            $table->unsignedBigInteger('primary_guard')->nullable()->virtualAs('if(is_primary, material_id, null)');
            $table->primary(['material_id', 'category_id']);
            $table->unique('primary_guard');
            $table->index('category_id');
        });

        Schema::create('material_collaborators', function (Blueprint $table) {
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->primary(['material_id', 'teacher_id']);
            $table->index('teacher_id');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('material_tags', function (Blueprint $table) {
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['material_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('material_collaborators');
        Schema::dropIfExists('material_categories');
        Schema::dropIfExists('material_files');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('video_assets');
    }
};
