<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | Fully-qualified class name of the Eloquent model that implements
    | Fazzinipierluigi\CrmCore\Contracts\CrmUser. The host application
    | binds its own User model here.
    |
    */
    'user_model' => env('CRM_USER_MODEL', \App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Route prefix & middleware
    |--------------------------------------------------------------------------
    |
    | Applied to the route group loaded from the package's routes/web.php.
    |
    */
    'route_prefix' => env('CRM_ROUTE_PREFIX', ''),

    'route_middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Entities
    |--------------------------------------------------------------------------
    */
    'entities' => [
        /*
        | Models that a "Relazione" field can point to besides other
        | Entities. Fully-qualified class name => display label, used to
        | populate the target picker in the entity builder.
        */
        'relatable_models' => [
            \App\Models\User::class => 'Utente',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tabler Icons (SVG library)
    |--------------------------------------------------------------------------
    |
    | Icons are always printed as inline SVG markup — never the icon
    | webfont — via the icon() helper (src/helpers.php) and its JS
    | equivalent (resources/js/icon.js). Both read straight from the
    | @tabler/icons npm package's static SVG files, so 'path' assumes the
    | host application has that package installed under its own
    | node_modules.
    |
    */
    'icons' => [
        'path' => env('CRM_ICONS_PATH', base_path('node_modules/@tabler/icons/icons')),

        'default_variant' => 'outline',
    ],

    /*
    |--------------------------------------------------------------------------
    | User preferences
    |--------------------------------------------------------------------------
    |
    | Per-user settings (stored in the `settings` table) exposed on the
    | personal settings page. Each entry lists the allowed values
    | (value => label) and the default applied when the user (and the
    | global fallback) has no value set.
    |
    | 'language' is intentionally not defined here — its options are
    | sourced dynamically from the `languages` table (Fazzinipierluigi\
    | CrmCore\Models\Language) instead of a static list, so admins can
    | add a language at runtime. Use the preferences() helper
    | (src/helpers.php), not config('crm.preferences') directly,
    | wherever you need the full preference set including language.
    |
    */
    'preferences' => [
        'date_format' => [
            'default' => 'd/m/Y',
            'options' => [
                'd/m/Y' => '31/12/2026',
                'm/d/Y' => '12/31/2026',
                'Y-m-d' => '2026-12-31',
            ],
        ],

        'number_format' => [
            'default' => 'it',
            'options' => [
                'it' => '1.234,56',
                'en' => '1,234.56',
            ],
        ],

        'theme' => [
            'default' => 'light',
            'options' => [
                'light' => 'Chiaro',
                'dark' => 'Scuro',
            ],
        ],

        // Maps to Tabler's `data-bs-theme-base` attribute (the neutral/gray
        // scale used for backgrounds, borders and body text).
        'theme_base' => [
            'default' => 'gray',
            'options' => [
                'gray' => 'Grigio',
                'slate' => 'Ardesia',
                'zinc' => 'Zinco',
                'neutral' => 'Neutro',
                'stone' => 'Pietra',
                'pink' => 'Rosa',
            ],
        ],

        // Maps to Tabler's `data-bs-theme-primary` attribute (the accent
        // color used for links, primary buttons, active states, etc.).
        'theme_color' => [
            'default' => 'blue',
            'options' => [
                'blue' => 'Blu',
                'azure' => 'Azzurro',
                'indigo' => 'Indaco',
                'purple' => 'Viola',
                'pink' => 'Rosa',
                'red' => 'Rosso',
                'orange' => 'Arancione',
                'yellow' => 'Giallo',
                'lime' => 'Lime',
                'green' => 'Verde',
                'teal' => 'Verde acqua',
                'cyan' => 'Ciano',
            ],
        ],
    ],

];
