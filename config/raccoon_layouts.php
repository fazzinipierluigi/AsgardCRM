<?php

use App\Models\User;

return [
    'route_prefix' => 'raccoon-layouts',
    'middleware' => ['web', 'auth'],
    'user_model' => User::class,
    'page_key_strategy' => 'url', // 'url' | 'route_name'
    'locale' => 'it', // 'en' | 'it' | 'es' | 'fr' | 'de'
];
