<?php

namespace Fazzinipierluigi\CrmCore\Console\Commands;

use Fazzinipierluigi\CrmCore\Jobs\SyncConnectorJob;
use Fazzinipierluigi\CrmCore\Models\Connector;
use Illuminate\Console\Command;

/**
 * Dispatches a SyncConnectorJob for every active Connector that's due —
 * never synced yet, or whose sync_interval_minutes has elapsed since
 * last_synced_at. Due-ness is computed in PHP rather than a DB-specific
 * date expression, so it behaves identically on MariaDB (production)
 * and sqlite (tests).
 */
class SyncCalendarConnectors extends Command
{
    protected $signature = 'calendar:sync-connectors {--connector= : Sync only the connector with this slug, regardless of due-ness}';

    protected $description = 'Dispatch sync jobs for every active calendar connector that is due for a sync';

    public function handle(): int
    {
        $slug = $this->option('connector');

        $connectors = Connector::where('is_active', true)
            ->when($slug, fn ($query) => $query->where('slug', $slug))
            ->get()
            ->filter(fn (Connector $connector) => $slug !== null || $this->isDue($connector));

        foreach ($connectors as $connector) {
            SyncConnectorJob::dispatch($connector);
        }

        $this->info("Dispatched sync for {$connectors->count()} connector(s).");

        return self::SUCCESS;
    }

    private function isDue(Connector $connector): bool
    {
        return $connector->last_synced_at === null
            || $connector->last_synced_at->addMinutes($connector->sync_interval_minutes)->isPast();
    }
}
