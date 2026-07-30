<?php

namespace App\Providers;

use App\Models\EntityRecord;
use App\Services\EnvFileWriter;
use App\Services\Workflows\WorkflowActionExecutor;
use App\Services\Workflows\WorkflowEntityTriggerDispatcher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton so a Redirect action executed deep inside
        // WorkflowEngine::completeUserTask() can hand its computed URL
        // back to WorkflowUserTaskController::update() (same request,
        // same container) via WorkflowActionExecutor::$lastRedirectUrl.
        $this->app->singleton(WorkflowActionExecutor::class);

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
        EntityRecord::created(fn (EntityRecord $record) => app(WorkflowEntityTriggerDispatcher::class)->handleCreated($record));
        EntityRecord::updated(fn (EntityRecord $record) => app(WorkflowEntityTriggerDispatcher::class)->handleUpdated($record));
    }
}
