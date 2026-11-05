<?php

namespace App\Modules\Communication\Services;

class FirebaseCredentials
{
    /** @return array<string, string>|null */
    public static function load(): ?array
    {
        $path = (string) config('services.fcm.credentials');

        if ($path === '') {
            return null;
        }

        $path = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) ? $path : base_path($path);

        if (! is_file($path)) {
            return null;
        }

        $credentials = json_decode((string) file_get_contents($path), true);

        return isset($credentials['project_id'], $credentials['client_email'], $credentials['private_key']) ? $credentials : null;
    }
}
