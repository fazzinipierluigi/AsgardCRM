<?php

use App\Models\User;

return [
    /*
    |--------------------------------------------------------------------------
    | Relatable system models
    |--------------------------------------------------------------------------
    |
    | Models that a "Relazione" field can point to besides other Entities.
    | Listed as fully-qualified class name => display label, used to
    | populate the target picker in the entity builder.
    |
    */

    'relatable_models' => [
        User::class => 'Utente',
    ],
];
