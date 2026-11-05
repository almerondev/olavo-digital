<?php

namespace App\Support;

/** Entende os formatos de link do YouTube: watch, youtu.be, live, embed e shorts. */
final class YouTubeUrl
{
    public static function id(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $id = match (true) {
            $host === 'youtu.be' => explode('/', $path)[0],
            in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com'], true) => $query['v']
                ?? (preg_match('#^(?:live|embed|shorts)/([^/]+)#', $path, $match) ? $match[1] : null),
            default => null,
        };

        return is_string($id) && preg_match('/^[\w-]{6,20}$/', $id) ? $id : null;
    }

    public static function embed(?string $url, array $params = []): ?string
    {
        $id = self::id($url);

        return $id ? "https://www.youtube-nocookie.com/embed/{$id}".($params ? '?'.http_build_query($params) : '') : null;
    }
}
