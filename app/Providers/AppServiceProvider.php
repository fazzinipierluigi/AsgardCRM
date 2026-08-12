<?php

namespace App\Providers;

use App\Services\EnvFileWriter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bound to the real .env path by default; tests bind a temp-file
        // instance so the installation wizard never touches the project's
        // actual .env.
        $this->app->singleton(EnvFileWriter::class, fn () => new EnvFileWriter(base_path('.env')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // EntityRecord::created/updated -> WorkflowEntityTriggerDispatcher
        // and the WorkflowActionExecutor singleton now live in
        // Fazzinipierluigi\CrmCore\CrmServiceProvider (Modulo 1 del
        // package crm-core) — vedi docs/package-conversion/03-migrazione-moduli.md.
    }
}
