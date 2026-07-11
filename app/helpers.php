<?php

use App\Models\Language;
use App\Models\Translation;

if (! function_exists('preferences')) {
    /**
     * The full user-preferences definition (config/preferences.php), with
     * the "language" entry's options sourced dynamically from the
     * `languages` table instead of a static config array — use this
     * instead of config('preferences') directly wherever "language" is
     * involved (its default is config('app.locale')).
     *
     * @return array<string, array{default: string, options: array<string, string>}>
     */
    function preferences(): array
    {
        $preferences = config('preferences');

        $preferences['language'] = [
            'default' => config('app.locale'),
            'options' => Language::options(),
        ];

        return $preferences;
    }
}

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
