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

    public function flush(): void
    {
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }
}
