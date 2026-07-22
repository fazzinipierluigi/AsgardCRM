<?php

namespace App\Providers;

use App\Models\EntityRecord;
use App\Services\Workflows\WorkflowEntityTriggerDispatcher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
