<?php

namespace Fazzinipierluigi\AsgardCRM\Console\Commands;

use Cron\CronExpression;
use Fazzinipierluigi\AsgardCRM\Enums\ImporterScheduleType;
use Fazzinipierluigi\AsgardCRM\Jobs\RunImporterJob;
use Fazzinipierluigi\AsgardCRM\Models\Importer;
use Illuminate\Console\Command;
use Throwable;

/**
 * Dispatches a RunImporterJob for every active Importer scheduled on
 * cron (or both) whose cron_expression is due right now. Due-ness is
 * computed in PHP against each importer's own expression, mirroring
 * SyncCalendarConnectors::isDue() — a single schedule entry checks
 * every row itself rather than one cron entry being registered per
 * importer.
 */
class RunDueImporters extends Command
{
    protected $signature = 'importers:run-due {--importer= : Esegue solo l\'importatore con questo slug, indipendentemente dalla scadenza}';

    protected $description = 'Dispatch run jobs for every active importer that is due according to its cron expression';

    public function handle(): int
    {
        $slug = $this->option('importer');

        $importers = Importer::where('is_active', true)
            ->whereIn('schedule_type', [ImporterScheduleType::Cron->value, ImporterScheduleType::Both->value])
            ->when($slug, fn ($query) => $query->where('slug', $slug))
            ->get()
            ->filter(fn (Importer $importer) => $slug !== null || $this->isDue($importer));

        foreach ($importers as $importer) {
            RunImporterJob::dispatch($importer);
        }

        $this->info("Dispatched run for {$importers->count()} importer(s).");

        return self::SUCCESS;
    }

    private function isDue(Importer $importer): bool
    {
        if ($importer->cron_expression === null) {
            return false;
        }

        try {
            $cron = new CronExpression($importer->cron_expression);
        } catch (Throwable) {
            return false;
        }

        if (! $cron->isDue()) {
            return false;
        }

        return $importer->last_run_at === null || $importer->last_run_at->lt(now()->startOfMinute());
    }
}
