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

];
