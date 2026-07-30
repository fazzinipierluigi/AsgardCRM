<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Version Upgrade Steps
    |--------------------------------------------------------------------------
    |
    | Ordered (ascending by version) list of App\Services\Upgrades\UpgradeStep
    | implementations. App\Services\VersionUpgradeRunner runs the ->upgrade()
    | of every step between the database's recorded version and the
    | deployed code's version (config('app.version')) — or the ->downgrade()
    | of every step in between, in reverse, when rolling back.
    |
    | Empty today: nothing has needed one yet.
    |
    */

    'steps' => [
        //
    ],

];
