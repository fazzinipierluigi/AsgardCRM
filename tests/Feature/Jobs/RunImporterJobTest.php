<?php

use Fazzinipierluigi\CrmCore\Enums\ImporterRunStatus;
use Fazzinipierluigi\CrmCore\Jobs\RunImporterJob;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\Importer;
use Fazzinipierluigi\CrmCore\Services\Importers\ImporterRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

function jobImporter(): Importer
{
    $entity = Entity::create(['name' => 'Contatti Job', 'slug' => 'contatti-job-'.uniqid(), 'table_name' => 'entity_contatti_job_'.uniqid()]);

    return Importer::create([
        'title' => 'Import job test',
        'entity_id' => $entity->id,
        'slug' => 'import-job-'.uniqid(),
        'channel' => 'csv',
        'config' => ['path_or_url' => '/tmp/x.csv'],
        'field_mapping' => ['nome' => 'nome'],
        'schedule_type' => 'manual',
    ]);
}

test('dispatching the job queues it without running it', function () {
    Queue::fake();
    $importer = jobImporter();

    RunImporterJob::dispatch($importer);

    Queue::assertPushed(RunImporterJob::class, fn ($job) => $job->importer->is($importer));
});

test('handling the job calls ImporterRunner::run', function () {
    $importer = jobImporter();

    $this->mock(ImporterRunner::class, function (MockInterface $mock) use ($importer) {
        $mock->shouldReceive('run')->once()->with(Mockery::on(fn ($i) => $i->is($importer)));
    });

    (new RunImporterJob($importer))->handle(app(ImporterRunner::class));
});

test('failed() records the failure on the importer', function () {
    $importer = jobImporter();
    $job = new RunImporterJob($importer);

    $job->failed(new RuntimeException('Boom'));

    $fresh = $importer->fresh();
    expect($fresh->last_run_status)->toBe(ImporterRunStatus::Failed->value);
    expect($fresh->last_run_message)->toBe('Boom');
});
