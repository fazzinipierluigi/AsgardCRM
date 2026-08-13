<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Importers;

use Fazzinipierluigi\AsgardCRM\Enums\ImporterRunStatus;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Models\Importer;
use Fazzinipierluigi\AsgardCRM\Models\ImporterRun;
use Throwable;

/**
 * Executes one Importer end to end: pulls every row from its channel,
 * maps it onto the target Entity's columns, and writes an EntityRecord.
 * Each row is isolated in its own try/catch — one bad row increments
 * rows_failed but never aborts the rest of the run (same reasoning as
 * CalendarSyncService::syncConnector() isolating one mailbox's failure
 * from the others).
 */
class ImporterRunner
{
    public function __construct(private readonly ImporterChannelFactory $factory) {}

    public function run(Importer $importer): ImporterRun
    {
        $run = $importer->runs()->create([
            'started_at' => now(),
            'status' => ImporterRunStatus::Running->value,
        ]);

        $imported = 0;
        $failed = 0;
        $errors = [];

        try {
            $channel = $this->factory->make($importer);
            $entity = $importer->entity;

            foreach ($channel->fetch($importer) as $sourceRow) {
                try {
                    $this->importRow($importer, $entity, $sourceRow);
                    $imported++;
                } catch (Throwable $e) {
                    $failed++;
                    $errors[] = $e->getMessage();
                }
            }

            $status = $failed === 0 ? ImporterRunStatus::Success : ImporterRunStatus::PartialFailure;
        } catch (Throwable $e) {
            $status = ImporterRunStatus::Failed;
            $errors[] = $e->getMessage();
        }

        $errorMessage = $errors === [] ? null : implode(' | ', array_slice($errors, 0, 20));

        $run->update([
            'finished_at' => now(),
            'status' => $status->value,
            'rows_imported' => $imported,
            'rows_failed' => $failed,
            'error_message' => $errorMessage,
        ]);

        $importer->update([
            'last_run_at' => $run->finished_at,
            'last_run_status' => $status->value,
            'last_run_message' => $errorMessage,
        ]);

        return $run;
    }

    /**
     * @param  array<string, mixed>  $sourceRow
     */
    private function importRow(Importer $importer, Entity $entity, array $sourceRow): void
    {
        // Every dynamic entity table requires a non-null user_id owner (see
        // EntitySchemaBuilder::create()) — a cron-triggered run has no
        // authenticated user of its own, so imported records are
        // attributed to whoever created the importer instead.
        $attributes = ['user_id' => $importer->created_by];

        foreach ($importer->field_mapping ?? [] as $sourceField => $columnName) {
            $attributes[$columnName] = $sourceRow[$sourceField] ?? null;
        }

        $query = EntityRecord::forEntity($entity)->newQuery();

        if ($importer->unique_key_field !== null && array_key_exists($importer->unique_key_field, $attributes)) {
            $query->updateOrCreate([$importer->unique_key_field => $attributes[$importer->unique_key_field]], $attributes);
        } else {
            $query->create($attributes);
        }
    }
}
