<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_layout_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('description', 500)->nullable();
            $table->string('source_type', 30);
            $table->json('source_config')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('layout', 20)->default('carousel');
            $table->unsignedSmallInteger('max_items')->default(20);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['home_layout_id', 'is_active', 'sort_order']);
        });

        Schema::create('home_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['home_section_id', 'material_id']);
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_layout_id')->constrained()->cascadeOnDelete();
            $table->string('placement', 20)->default('hero');
            $table->string('title')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->foreignId('image_upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_url', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['home_layout_id', 'placement', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
        Schema::dropIfExists('home_section_items');
        Schema::dropIfExists('home_sections');
        Schema::dropIfExists('home_layouts');
    }
};
