<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name', 100);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 60)->nullable();
            $table->foreignId('cover_upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('visibility', 20)->default('public');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['parent_id', 'sort_order']);
            $table->index(['category_type_id', 'is_active']);
        });

        Schema::create('teacher_categories', function (Blueprint $table) {
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_publish')->default(true);
            $table->primary(['teacher_id', 'category_id']);
            $table->index('category_id');
        });

        // Sem CHECK: o MySQL proíbe CHECK em colunas com FK em cascata. HasAudience é o único escritor.
        Schema::create('audience_targets', function (Blueprint $table) {
            $table->id();
            $table->morphs('targetable');
            $table->foreignId('student_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['targetable_type', 'targetable_id', 'student_group_id'], 'audience_group_unique');
            $table->unique(['targetable_type', 'targetable_id', 'student_id'], 'audience_student_unique');
            $table->index(['student_group_id', 'targetable_type', 'targetable_id'], 'audience_group_lookup');
            $table->index(['student_id', 'targetable_type', 'targetable_id'], 'audience_student_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_targets');
        Schema::dropIfExists('teacher_categories');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('category_types');
    }
};
