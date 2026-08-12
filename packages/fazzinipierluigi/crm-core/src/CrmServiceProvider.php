<?php

namespace Fazzinipierluigi\CrmCore;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/crm.php', 'crm');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'crm');

        Route::group([
            'prefix' => config('crm.route_prefix', ''),
            'middleware' => config('crm.route_middleware', ['web']),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/crm.php' => config_path('crm.php'),
            ], 'crm-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'crm-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/crm'),
            ], 'crm-views');
        }
    }
}
