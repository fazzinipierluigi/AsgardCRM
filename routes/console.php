<?php

use App\Console\Commands\RunDueImporters;
use App\Console\Commands\SyncCalendarConnectors;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Runs every minute but only actually dispatches a sync for connectors
// whose own sync_interval_minutes has elapsed (see
// SyncCalendarConnectors::isDue()) — the per-connector interval is
// enforced in the command itself, not by this schedule's own cadence.
Schedule::command(SyncCalendarConnectors::class)->everyMinute()->withoutOverlapping();

// Same pattern as above: the per-importer cron_expression due-ness is
// evaluated inside RunDueImporters::isDue(), not by this schedule.
Schedule::command(RunDueImporters::class)->everyMinute()->withoutOverlapping();
