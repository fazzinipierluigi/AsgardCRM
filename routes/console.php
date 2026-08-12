<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// RunDueImporters/RunDueWorkflows/FireDueWorkflowTimers/
// SyncCalendarConnectors sono ora schedulati da
// Fazzinipierluigi\CrmCore\CrmServiceProvider (Moduli 1 e 2 del
// package crm-core) — vedi docs/package-conversion/03-migrazione-moduli.md.
