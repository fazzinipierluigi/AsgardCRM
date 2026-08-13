<?php

namespace Fazzinipierluigi\AsgardCRM\Tests;

use Fazzinipierluigi\AsgardCRM\AsgardCRMServiceProvider;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Fazzinipierluigi\JustAGate\JustAGateServiceProvider;
use Fazzinipierluigi\LaraccoonLayouts\RaccoonLayoutsServiceProvider;
use Illuminate\Support\Facades\Route;
use LdapRecord\Laravel\LdapServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The package has no asset pipeline of its own yet (Fase 1
        // punto 8, not implemented) — views still call @vite() as
        // copied from the host app, so tests don't need a real
        // manifest to render.
        $this->withoutVite();

        // fazzinipierluigi/laraccoon-layouts (a real dependency, used by
        // the datagrid persistence feature on EntityController/
        // WorkflowController/ImporterController) doesn't self-load its
        // migrations the way just-a-gate does — it only exposes them for
        // `vendor:publish`, expecting the host to commit the published
        // copy (see database/migrations/*_create_raccoon_layouts_table.php
        // in the root app). Loaded here so the datagrid_layouts table
        // exists under Testbench too.
        $this->loadMigrationsFrom(__DIR__.'/../vendor/fazzinipierluigi/laraccoon-layouts/database/migrations');

        // Test-only stand-in for the host's own `users` table — see the
        // migration file's own docblock for why this must be a real
        // migration (picked up by a real `Artisan::call('migrate')`, as
        // ApplicationInstaller runs) rather than an inline Schema::create.
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // The two loadMigrationsFrom() calls above go through Testbench's
        // own TestCase::loadMigrationsFrom() (Orchestra\Testbench\Concerns\
        // InteractsWithMigrations), which — with RefreshDatabase active —
        // only bookkeeps the path for RefreshDatabase's own one-time lazy
        // migration run (load_migration_paths()); it never calls the real
        // Migrator::path(), so the path never shows up in
        // app('migrator')->paths(). That's invisible to the rest of this
        // suite (RefreshDatabase's own lazy migrate still picks it up
        // correctly), but ApplicationInstaller::install() calls a real,
        // later `Artisan::call('migrate')` (see InstallWizardTest) against
        // a *different*, non-RefreshDatabase-managed connection — that
        // migrate run only sees paths registered via the real
        // Migrator::path(), the mechanism ServiceProvider::loadMigrationsFrom()
        // uses. Registered a second time here, directly on the real
        // Migrator, so both mechanisms see these two paths.
        $migrator = $this->app->make('migrator');
        $migrator->path(__DIR__.'/../vendor/fazzinipierluigi/laraccoon-layouts/database/migrations');
        $migrator->path(__DIR__.'/database/migrations');

        // layouts.admin/layouts.base are a documented host contract (not
        // shipped/prefixed crm:: by the package) — stub them here so the
        // module's real views can compile under Testbench.
        $this->app['view']->addLocation(__DIR__.'/resources/views');

        // dashboard is a host-provided named route (out of this
        // package's scope) — stubbed so redirects triggered by the
        // `auth` middleware / post-action redirects resolve. Renders
        // through the same layouts.base stub as every real package page
        // (not a bare string) so structural checks that don't depend on
        // host business logic — csrf-token meta tag, etc. — still pass.
        // login is a real package route since Modulo 5
        // (Auth/Admin/Install/Update), no longer stubbed here.
        Route::get('/dashboard', fn () => view('dashboard-stub'))->name('dashboard');

        // /up is normally registered by a real app's own
        // bootstrap/app.php (->withRouting(health: '/up')) — Testbench's
        // synthetic skeleton doesn't add one. EnsureAppIsInstalled/
        // EnsureAppIsUpToDate explicitly bypass it either way.
        Route::get('/up', fn () => 'ok');
    }

    protected function getPackageProviders($app): array
    {
        return [
            JustAGateServiceProvider::class,
            RaccoonLayoutsServiceProvider::class,
            LdapServiceProvider::class,
            AsgardCRMServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        // Not a stock Laravel config key — the update wizard
        // (VersionUpdateService) compares it against the database's
        // recorded app_version Setting. A real host declares its own
        // in config/app.php (see AsgardCRM's own config/app.php).
        $app['config']->set('app.version', '1.0.0');
        $app['config']->set('crm.user_model', User::class);
        $app['config']->set('auth.providers.users.model', User::class);

        // config('crm.entities.relatable_models') defaults to
        // [\App\Models\User::class => 'Utente'] — correct for a real
        // host app, but the App\ namespace doesn't exist at all under
        // this package's own Testbench environment. Point it at the
        // test fixture instead, so the "link a calendar event to a
        // relatable record" feature has something real to query.
        $app['config']->set('crm.entities.relatable_models', [
            User::class => 'Utente',
        ]);

        // config('crm.icons.path') defaults to base_path('node_modules/@tabler/icons/icons')
        // — correct for a real host app (see config/crm.php), but under
        // Testbench base_path() resolves inside vendor/orchestra/testbench-core's
        // own synthetic skeleton app, not this package. Point it at the
        // package's own node_modules (a real devDependency, see package.json)
        // instead, so icon_names()/icon() resolve real SVGs under test.
        $app['config']->set('crm.icons.path', __DIR__.'/../node_modules/@tabler/icons/icons');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }
}
