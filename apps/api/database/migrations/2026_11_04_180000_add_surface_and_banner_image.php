<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_layouts', function (Blueprint $table) {
            $table->string('surface', 20)->default('home')->after('name');
            $table->index(['surface', 'status']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('banner_upload_id')->nullable()->after('cover_upload_id')->constrained('uploads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('banner_upload_id');
        });

        Schema::table('home_layouts', function (Blueprint $table) {
            $table->dropIndex(['surface', 'status']);
            $table->dropColumn('surface');
        });
    }
};
