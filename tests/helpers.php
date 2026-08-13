<?php

if (! function_exists('icon')) {
    /**
     * Minimal stand-in for the host application's own icon() helper
     * (app/helpers.php, backed by config('icons.path') SVG files — out
     * of Modulo 1's scope). Returns a placeholder tag carrying the icon
     * name so tests can still assertSee() a specific icon was requested.
     */
    function icon(string $name, ?string $variant = null): string
    {
        return '<i class="icon-'.$name.'"></i>';
    }
}

if (! function_exists('icon_names')) {
    /**
     * Minimal stand-in for the host application's own icon_names()
     * helper (app/helpers.php, backed by config('icons.path') scanning
     *
     * @tabler/icons SVGs — out of Modulo 1's scope). A small fixed set
     * is enough for validation rules like Rule::in(icon_names()).
     */
    function icon_names(?string $variant = null): array
    {
        return ['users', 'building', 'file', 'calendar', 'mail', 'settings'];
    }
}

if (! function_exists('t')) {
    /**
     * Minimal stand-in for the host application's own t() translation
     * helper (app/helpers.php, backed by Translation::valueFor() — out
     * of Modulo 1's scope). Falls back to the key itself with :search
     * replacements applied, same as the real helper's untranslated path.
     */
    function t(string $key, array $replace = [], ?string $locale = null): string
    {
        $value = $key;

        foreach ($replace as $search => $replacement) {
            $value = str_replace(':'.$search, $replacement, $value);
        }

        return $value;
    }
}
