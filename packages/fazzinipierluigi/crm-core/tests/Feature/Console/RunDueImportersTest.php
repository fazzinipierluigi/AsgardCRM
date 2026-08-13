<?php

use Fazzinipierluigi\AsgardCRM\Jobs\RunImporterJob;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\Importer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function runDueImportersEntity(): Entity
{
    return Entity::create(['name' => 'Contatti Cron', 'slug' => 'contatti-cron-'.uniqid(), 'table_name' => 'entity_contatti_cron_'.uniqid()]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function cronImporterFactory(array $overrides = []): Importer
{
    return Importer::create(array_merge([
        'title' => 'Import cron',
        'entity_id' => runDueImportersEntity()->id,
        'slug' => 'import-cron-'.uniqid(),
        'channel' => 'csv',
        'config' => ['path_or_url' => '/tmp/x.csv'],
        'field_mapping' => ['nome' => 'nome'],
        'schedule_type' => 'cron',
        'cron_expression' => '* * * * *',
        'is_active' => true,
    ], $overrides));
}

test('dispatches a job for an importer due right now', function () {
    Bus::fake();
    $importer = cronImporterFactory(['last_run_at' => null]);

    $this->artisan('importers:run-due')->assertSuccessful();

    Bus::assertDispatched(RunImporterJob::class, fn ($job) => $job->importer->is($importer));
});

test('does not dispatch a job for an importer whose cron is not due', function () {
    Bus::fake();
    cronImporterFactory(['cron_expression' => '0 0 1 1 *', 'last_run_at' => null]);

    $this->artisan('importers:run-due')->assertSuccessful();

    Bus::assertNotDispatched(RunImporterJob::class);
});

test('does not dispatch twice for the same minute', function () {
    Bus::fake();
    cronImporterFactory(['last_run_at' => now()]);

    $this->artisan('importers:run-due')->assertSuccessful();

    Bus::assertNotDispatched(RunImporterJob::class);
});

test('dispatches again once a new minute has started', function () {
    Bus::fake();
    $importer = cronImporterFactory(['last_run_at' => now()->subMinutes(2)]);

    $this->artisan('importers:run-due')->assertSuccessful();

    Bus::assertDispatched(RunImporterJob::class, fn ($job) => $job->importer->is($importer));
});

test('does not dispatch a job for a manual-only importer', function () {
    Bus::fake();
    cronImporterFactory(['schedule_type' => 'manual', 'cron_expression' => null]);

    $this->artisan('importers:run-due')->assertSuccessful();

    Bus::assertNotDispatched(RunImporterJob::class);
});

test('does not dispatch a job for an inactive importer', function () {
    Bus::fake();
    cronImporterFactory(['is_active' => false]);

    $this->artisan('importers:run-due')->assertSuccessful();

    Bus::assertNotDispatched(RunImporterJob::class);
});

test('--importer forces a single importer regardless of due-ness', function () {
    Bus::fake();
    $due = cronImporterFactory(['cron_expression' => '0 0 1 1 *']);
    cronImporterFactory(['last_run_at' => null]);

    $this->artisan('importers:run-due', ['--importer' => $due->slug])->assertSuccessful();

    Bus::assertDispatchedTimes(RunImporterJob::class, 1);
    Bus::assertDispatched(RunImporterJob::class, fn ($job) => $job->importer->is($due));
});
