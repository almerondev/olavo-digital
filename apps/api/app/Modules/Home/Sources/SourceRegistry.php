<?php

namespace App\Modules\Home\Sources;

use InvalidArgumentException;

class SourceRegistry
{
    /** @var array<string, class-string<SectionSource>> */
    private const SOURCES = [
        'manual' => ManualSource::class,
        'category' => CategorySource::class,
        'recent' => RecentSource::class,
        'featured' => FeaturedSource::class,
        'continue_watching' => ContinueWatchingSource::class,
        'recommended' => RecommendedSource::class,
        'teacher' => TeacherSource::class,
    ];

    public function for(string $type): SectionSource
    {
        $class = self::SOURCES[$type] ?? throw new InvalidArgumentException("Origem de seção desconhecida: {$type}");

        return app($class);
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::SOURCES);
    }

    public function supports(string $type): bool
    {
        return isset(self::SOURCES[$type]);
    }
}
