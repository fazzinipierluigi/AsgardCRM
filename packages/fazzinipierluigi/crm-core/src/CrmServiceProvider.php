<?php

namespace Fazzinipierluigi\CrmCore;

use Fazzinipierluigi\CrmCore\Console\Commands\BackfillInstalledEntityUpgrades;
use Fazzinipierluigi\CrmCore\Console\Commands\FireDueWorkflowTimers;
use Fazzinipierluigi\CrmCore\Console\Commands\RunDueImporters;
use Fazzinipierluigi\CrmCore\Console\Commands\RunDueWorkflows;
use Fazzinipierluigi\CrmCore\Console\Commands\SyncCalendarConnectors;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Services\Workflows\WorkflowActionExecutor;
use Fazzinipierluigi\CrmCore\Services\Workflows\WorkflowEntityTriggerDispatcher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/crm.php', 'crm');

        // Singleton so a Redirect action executed deep inside
        // WorkflowEngine::completeUserTask() can hand its computed URL
        // back to WorkflowUserTaskController::update() (same request,
        // same container) via WorkflowActionExecutor::$lastRedirectUrl.
        $this->app->singleton(WorkflowActionExecutor::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'crm');

        EntityRecord::created(fn (EntityRecord $record) => app(WorkflowEntityTriggerDispatcher::class)->handleCreated($record));
        EntityRecord::updated(fn (EntityRecord $record) => app(WorkflowEntityTriggerDispatcher::class)->handleUpdated($record));

        Route::group([
            'prefix' => config('crm.route_prefix', ''),
            'middleware' => config('crm.route_middleware', ['web']),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });

        $this->app->booted(function (): void {
            Schedule::command(RunDueImporters::class)->everyMinute()->withoutOverlapping();
            Schedule::command(RunDueWorkflows::class)->everyMinute()->withoutOverlapping();
            Schedule::command(FireDueWorkflowTimers::class)->everyMinute()->withoutOverlapping();
            Schedule::command(SyncCalendarConnectors::class)->everyMinute()->withoutOverlapping();
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/crm.php' => config_path('crm.php'),
            ], 'crm-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'crm-migrations');

            // fazzinipierluigi/laraccoon-layouts doesn't auto-load its own
            // migration (only exposes it for vendor:publish) — bundled
            // into our own crm-migrations tag so installing crm-core
            // yields a complete schema in one step.
            $this->publishes([
                base_path('vendor/fazzinipierluigi/laraccoon-layouts/database/migrations') => database_path('migrations'),
            ], 'crm-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/crm'),
            ], 'crm-views');

            // Pre-built Vite output (see packages/fazzinipierluigi/crm-core's
            // own package.json/vite.config.js, buildDirectory: 'vendor/crm')
            // — a Composer-installed package can't run `npm run build` on
            // the consuming host, so the compiled assets are committed and
            // published as-is. The plugin's buildDirectory MUST match this
            // publish target 1:1: paths inside the compiled manifest/font
            // CSS (e.g. @font-face src URLs) are baked in at build time,
            // not resolved at request time. Package views call
            // @vite([...], 'vendor/crm'), which reads
            // public_path('vendor/crm/manifest.json').
            $this->publishes([
                __DIR__.'/../public/vendor/crm' => public_path('vendor/crm'),
            ], 'crm-assets');

            // HugeRTE fetches its own runtime assets (skins/icons/plugins)
            // over plain HTTP from base_url: '/hugerte' (see
            // resources/js/hugerte.js) — not bundled by Vite. Published as
            // a real copy, not a symlink into node_modules (the consuming
            // host has no node_modules for this package at all).
            $this->publishes([
                __DIR__.'/../public/hugerte' => public_path('hugerte'),
            ], 'crm-assets');

            $this->commands([
                BackfillInstalledEntityUpgrades::class,
                FireDueWorkflowTimers::class,
                RunDueImporters::class,
                RunDueWorkflows::class,
                SyncCalendarConnectors::class,
            ]);
        }
    }
}
