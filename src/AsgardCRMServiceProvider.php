<?php

namespace Fazzinipierluigi\AsgardCRM;

use Fazzinipierluigi\AsgardCRM\Console\Commands\BackfillInstalledEntityUpgrades;
use Fazzinipierluigi\AsgardCRM\Console\Commands\FireDueWorkflowTimers;
use Fazzinipierluigi\AsgardCRM\Console\Commands\ResetInstallCommand;
use Fazzinipierluigi\AsgardCRM\Console\Commands\RunDueImporters;
use Fazzinipierluigi\AsgardCRM\Console\Commands\RunDueWorkflows;
use Fazzinipierluigi\AsgardCRM\Console\Commands\SyncCalendarConnectors;
use Fazzinipierluigi\AsgardCRM\Http\Middleware\ApplyUserPreferences;
use Fazzinipierluigi\AsgardCRM\Http\Middleware\EnsureAppIsInstalled;
use Fazzinipierluigi\AsgardCRM\Http\Middleware\EnsureAppIsUpToDate;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Services\EnvFileWriter;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\WorkflowActionExecutor;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\WorkflowEntityTriggerDispatcher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

class AsgardCRMServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/crm.php', 'crm');

        // Singleton so a Redirect action executed deep inside
        // WorkflowEngine::completeUserTask() can hand its computed URL
        // back to WorkflowUserTaskController::update() (same request,
        // same container) via WorkflowActionExecutor::$lastRedirectUrl.
        $this->app->singleton(WorkflowActionExecutor::class);

        // Bound to the real .env path by default; tests bind a temp-file
        // instance so the installation wizard never touches the
        // consuming host's actual .env.
        $this->app->singleton(EnvFileWriter::class, fn () => new EnvFileWriter(base_path('.env')));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'crm');

        // EnsureAppIsInstalled/EnsureAppIsUpToDate gate the whole app
        // (including host-defined routes outside this package, e.g. a
        // Scaffolding app's own dashboard), so they're registered as
        // aliases here — the host decides whether/where to apply them in
        // its own bootstrap/app.php, same as ApplyUserPreferences used to
        // be host-wired before Modulo 5. Not auto-appended to the 'web'
        // group globally: forcing them on would make it impossible for a
        // host to opt out (e.g. Testbench's own test apps, which have no
        // install wizard route to redirect to).
        $router = $this->app['router'];
        $router->aliasMiddleware('crm.installed', EnsureAppIsInstalled::class);
        $router->aliasMiddleware('crm.up-to-date', EnsureAppIsUpToDate::class);

        // ApplyUserPreferences only needs a resolved user (via the `web`
        // guard already active on the group below) and no install-wizard
        // awareness, so it's safe to push onto every host's 'web' group
        // unconditionally, unlike the two middleware above.
        $router->pushMiddlewareToGroup('web', ApplyUserPreferences::class);

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

            // Migration che alterano la tabella `users` dell'host
            // (username, login_provider_id, phone, job_title) — mai
            // auto-caricate (Fase 1 decisione 4b): un host con quelle
            // colonne gia' presenti, o con un proprio schema utenti,
            // non deve vederle applicate automaticamente da un
            // `artisan migrate` qualsiasi. Tag separato, esplicito.
            $this->publishes([
                __DIR__.'/../database/migrations-users' => database_path('migrations'),
            ], 'crm-migrations-users');

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

            // The 3 custom auth.provider_* keys (Modulo 5) live alongside
            // Laravel's own stock auth.php keys — bare `trans('auth.xxx')`
            // calls only resolve them once this is published into the
            // host's own lang_path(), same "not automatic, explicit tag"
            // caution as crm-migrations-users: a host with its own
            // customized lang/en/auth.php shouldn't have it silently
            // overwritten.
            $this->publishes([
                __DIR__.'/../lang' => lang_path(),
            ], 'crm-lang');

            $this->commands([
                BackfillInstalledEntityUpgrades::class,
                FireDueWorkflowTimers::class,
                ResetInstallCommand::class,
                RunDueImporters::class,
                RunDueWorkflows::class,
                SyncCalendarConnectors::class,
            ]);
        }
    }
}
