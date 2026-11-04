<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('enrollment_code', 40)->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_contact')->nullable();
            $table->string('external_ref', 100)->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('slug', 120)->unique();
            $table->string('display_name');
            $table->string('short_bio', 280)->nullable();
            $table->text('bio')->nullable();
            $table->unsignedBigInteger('photo_upload_id')->nullable();
            $table->unsignedBigInteger('banner_upload_id')->nullable();
            $table->json('specialties')->nullable();
            $table->string('publish_mode', 20)->default('inherit');
            $table->boolean('show_in_cast')->default(true);
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['show_in_cast', 'status', 'sort_order']);
        });

        Schema::create('teacher_social_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->string('network', 40);
            $table->string('url', 500);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['teacher_id', 'network']);
        });

        Schema::create('student_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 120)->unique();
            $table->string('kind', 20)->default('turma');
            $table->unsignedSmallInteger('year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['kind', 'is_active']);
        });

        Schema::create('student_group_members', function (Blueprint $table) {
            $table->foreignId('student_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->primary(['student_group_id', 'student_id']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_group_members');
        Schema::dropIfExists('student_groups');
        Schema::dropIfExists('teacher_social_links');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('students');
    }
};
