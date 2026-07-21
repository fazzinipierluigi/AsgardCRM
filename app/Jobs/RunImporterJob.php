<?php

namespace App\Jobs;

use App\Enums\ImporterRunStatus;
use App\Models\Importer;
use App\Services\Importers\ImporterRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs one Importer in the background — dispatched per-importer by
 * RunDueImporters (or directly from the admin "run now" action), so
 * one importer's slow source doesn't block another's run.
 */
class RunImporterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public int $tries = 3;

    public function __construct(public readonly Importer $importer) {}

    public function handle(ImporterRunner $runner): void
    {
        $runner->run($this->importer);
    }

    public function failed(Throwable $exception): void
    {
        $this->importer->update([
            'last_run_status' => ImporterRunStatus::Failed->value,
            'last_run_message' => $exception->getMessage(),
        ]);
    }
}
