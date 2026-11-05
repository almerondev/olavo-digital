<?php

namespace Database\Seeders;

use App\Modules\Cast\Models\Teacher;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Services\SettingsService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'platform.name' => 'Olavo Digital',
            'platform.tagline' => 'Seu pré-vestibular em qualquer tela.',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'is_public' => true]);
        }

        Setting::firstOrCreate(['key' => Teacher::PUBLISH_SETTING], ['value' => 'approval', 'is_public' => false]);

        app(SettingsService::class)->flush();
    }
}
