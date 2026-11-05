<?php

use App\Modules\Settings\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('push_subscriptions');

        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->text('token');
            $table->string('platform', 20)->default('android');
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->string('media_type', 20)->default('preview')->after('image_upload_id');
            $table->foreignId('video_upload_id')->nullable()->after('media_type')->constrained('uploads')->nullOnDelete();
        });

        Setting::where('key', 'platform.name')->get()
            ->filter(fn (Setting $setting) => $setting->value === 'Olavo Digital')
            ->each(fn (Setting $setting) => $setting->update(['value' => 'Olavo TV+']));
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('video_upload_id');
            $table->dropColumn('media_type');
        });

        Schema::dropIfExists('push_devices');
    }
};
