<?php

namespace Fazzinipierluigi\CrmCore\Tests;

use Fazzinipierluigi\CrmCore\CrmServiceProvider;
use Fazzinipierluigi\CrmCore\Tests\Fixtures\User;
use Fazzinipierluigi\JustAGate\JustAGateServiceProvider;
use Fazzinipierluigi\LaraccoonLayouts\RaccoonLayoutsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // layouts.admin/layouts.base are a documented host contract (not
        // shipped/prefixed crm:: by the package) — stub them here so the
        // module's real views can compile under Testbench.
        $this->app['view']->addLocation(__DIR__.'/resources/views');

        // login/dashboard are host-provided named routes (Auth module,
        // out of Modulo 1's scope) — stub them so redirects triggered by
        // the `auth` middleware / post-action redirects resolve.
        Route::get('/login', fn () => 'login stub')->name('login');
        Route::get('/dashboard', fn () => 'dashboard stub')->name('dashboard');
    }

    protected function getPackageProviders($app): array
    {
        return [
            JustAGateServiceProvider::class,
            RaccoonLayoutsServiceProvider::class,
            CrmServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('crm.user_model', User::class);
        $app['config']->set('auth.providers.users.model', User::class);

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
