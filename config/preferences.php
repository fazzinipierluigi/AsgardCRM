<?php

return [
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
    */

    'date_format' => [
        'default' => 'd/m/Y',
        'options' => [
            'd/m/Y' => '31/12/2026',
            'm/d/Y' => '12/31/2026',
            'Y-m-d' => '2026-12-31',
        ],
    ],

    'language' => [
        'default' => 'it',
        'options' => [
            'it' => 'Italiano',
            'en' => 'English',
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
];
