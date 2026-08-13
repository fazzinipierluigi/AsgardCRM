<?php

namespace Fazzinipierluigi\AsgardCRM\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'language', 'value'])]
class Translation extends Model
{
    /**
     * Per-language lookup cache (key => value), populated lazily and kept
     * for the lifetime of the request/process.
     *
     * @var array<string, array<string, string>>
     */
    private static array $cache = [];

    /**
     * Look up a translated value for the given key/language pair, or null
     * if none exists.
     */
    public static function valueFor(string $key, ?string $language): ?string
    {
        if ($language === null) {
            return null;
        }

        if (! isset(static::$cache[$language])) {
            static::$cache[$language] = static::query()
                ->where('language', $language)
                ->pluck('value', 'key')
                ->all();
        }

        return static::$cache[$language][$key] ?? null;
    }

    /**
     * Forget the in-memory lookup cache. Call this after writing a
     * translation so subsequent t() calls (in the same request) see it.
     */
    public static function forgetCache(): void
    {
        static::$cache = [];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetCache());
        static::deleted(fn () => static::forgetCache());
    }
}
