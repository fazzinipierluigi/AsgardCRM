<?php

namespace App\Jobs;

use App\Models\Connector;
use App\Services\Connectors\CalendarSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs one Connector's sync in the background — dispatched per-connector
 * by SyncCalendarConnectors, so one connector's mailboxes/API being slow
 * or down doesn't block another connector's sync run.
 */
class SyncConnectorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public int $tries = 3;

    public function __construct(public readonly Connector $connector) {}

    public function handle(CalendarSyncService $service): void
    {
        $service->syncConnector($this->connector);
    }

    public function failed(Throwable $exception): void
    {
        $this->connector->update([
            'last_sync_status' => 'failed',
            'last_sync_message' => $exception->getMessage(),
        ]);
    }
}
