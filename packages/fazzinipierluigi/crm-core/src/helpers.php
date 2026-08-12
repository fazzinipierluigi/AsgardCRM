<?php

use Fazzinipierluigi\CrmCore\Models\Language;
use Fazzinipierluigi\CrmCore\Models\Translation;

if (! function_exists('preferences')) {
    /**
     * The full user-preferences definition (config('crm.preferences')),
     * with the "language" entry's options sourced dynamically from the
     * `languages` table instead of a static config array — use this
     * instead of config('crm.preferences') directly wherever "language"
     * is involved (its default is config('app.locale')).
     *
     * @return array<string, array{default: string, options: array<string, string>}>
     */
    function preferences(): array
    {
        $preferences = config('crm.preferences');

        $preferences['language'] = [
            'default' => config('app.locale'),
            'options' => Language::options(),
        ];

        return $preferences;
    }
}

if (! function_exists('icon')) {
    /**
     * Inline SVG markup for a Tabler icon, read straight from the Tabler
     * Icons npm package's static SVG files (config('crm.icons.path')).
     *
     * New-code rule: never load the icon webfont — always print an
     * icon's actual SVG content into the page via this helper (or its JS
     * equivalent, resources/js/icon.js, for markup built client-side).
     *
     * $name/$variant are reduced to their basename before touching the
     * filesystem, so a caller can never escape the icons directory.
     * Returns an empty string (not an exception) for an unknown icon —
     * callers render icons inline in markup, where a missing file
     * should degrade to "no icon", not a fatal error.
     */
    function icon(string $name, ?string $variant = null): string
    {
        static $cache = [];

        $variant = basename($variant ?? config('crm.icons.default_variant'));
        $name = basename($name);
        $key = "{$variant}/{$name}";

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $path = config('crm.icons.path')."/{$variant}/{$name}.svg";

        return $cache[$key] = is_file($path) ? file_get_contents($path) : '';
    }
}

if (! function_exists('icon_names')) {
    /**
     * Every icon name available for a given Tabler Icons variant — sorted,
     * without the .svg extension. Backs the entity icon <select> (see
     * admin/entities/_form.blade.php); values are what icon() expects as
     * $name.
     *
     * @return array<int, string>
     */
    function icon_names(?string $variant = null): array
    {
        static $cache = [];

        $variant = basename($variant ?? config('crm.icons.default_variant'));

        if (array_key_exists($variant, $cache)) {
            return $cache[$variant];
        }

        $names = collect(glob(config('crm.icons.path')."/{$variant}/*.svg") ?: [])
            ->map(fn (string $file) => basename($file, '.svg'))
            ->sort()
            ->values()
            ->all();

        return $cache[$variant] = $names;
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
