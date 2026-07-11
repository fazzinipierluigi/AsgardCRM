<?php

use App\Models\Translation;

if (! function_exists('t')) {
    /**
     * Translate the given key using the database-backed translations table.
     *
     * Resolution order: the authenticated user's "language" preference,
     * then the app's locale (config('app.locale'), i.e. APP_LOCALE from
     * .env). If no translation exists in either language, the key itself
     * is returned (matching Laravel's own trans() behavior for missing
     * translations).
     *
     * @param  array<string, string>  $replace
     */
    function t(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= auth()->check() ? auth()->user()->getSetting('language') : null;
        $locale ??= config('app.locale');

        $value = Translation::valueFor($key, $locale);

        if ($value === null && $locale !== config('app.locale')) {
            $value = Translation::valueFor($key, config('app.locale'));
        }

        $value ??= $key;

        foreach ($replace as $search => $replacement) {
            $value = str_replace(':'.$search, $replacement, $value);
        }

        return $value;
    }
}
