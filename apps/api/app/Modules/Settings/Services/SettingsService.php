<?php

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const PUBLIC_CACHE_KEY = 'settings:public';

    /** @return array<string, mixed> */
    public function public(): array
    {
        return Cache::rememberForever(
            self::PUBLIC_CACHE_KEY,
            fn () => Setting::where('is_public', true)->pluck('value', 'key')->all(),
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::where('key', $key)->value('value') ?? $default;
    }

    public function set(string $key, mixed $value, bool $public = false): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'is_public' => $public]);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }
}
