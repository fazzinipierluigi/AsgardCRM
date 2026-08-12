<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Enums\ImporterRunStatus;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use Fazzinipierluigi\CrmCore\Models\Importer;
use App\Models\User;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Fazzinipierluigi\CrmCore\Services\Importers\ImporterRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function runnerContactsEntity(): Entity
{
    $entity = Entity::create(['name' => 'Contatti Runner', 'slug' => 'contatti-runner-'.uniqid(), 'table_name' => 'entity_contatti_runner_'.uniqid()]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'required' => true, 'position' => 0]);
    $card->fields()->create(['name' => 'Email', 'column_name' => 'email', 'type' => EntityFieldType::String, 'position' => 1]);

    app(EntityInstaller::class)->install($entity);

    return $entity;
}

function csvImporterFor(Entity $entity, string $csvContent, ?string $uniqueKeyField = null): Importer
{
    $path = tempnam(sys_get_temp_dir(), 'importer_runner_csv_');
    file_put_contents($path, $csvContent);

    return Importer::create([
        'title' => 'Import runner test',
        'entity_id' => $entity->id,
        'created_by' => User::factory()->create()->id,
        'slug' => 'import-runner-'.uniqid(),
        'channel' => 'csv',
        'config' => ['path_or_url' => $path, 'delimiter' => ',', 'has_header' => true],
        'field_mapping' => ['nome' => 'nome', 'email' => 'email'],
        'unique_key_field' => $uniqueKeyField,
        'schedule_type' => 'manual',
    ]);
}

test('run creates one entity record per source row when there is no unique key', function () {
    $entity = runnerContactsEntity();
    $importer = csvImporterFor($entity, "nome,email\nMario Rossi,mario@example.com\nLuigi Verdi,luigi@example.com\n");

    $run = app(ImporterRunner::class)->run($importer);

    expect($run->status)->toBe(ImporterRunStatus::Success);
    expect($run->rows_imported)->toBe(2);
    expect($run->rows_failed)->toBe(0);
    expect(EntityRecord::forEntity($entity)->count())->toBe(2);

    $importer->refresh();
    expect($importer->last_run_status)->toBe(ImporterRunStatus::Success->value);
    expect($importer->last_run_at)->not->toBeNull();
});

test('running the same source twice without a unique key duplicates the rows', function () {
    $entity = runnerContactsEntity();
    $importer = csvImporterFor($entity, "nome,email\nMario Rossi,mario@example.com\n");

    app(ImporterRunner::class)->run($importer);
    app(ImporterRunner::class)->run($importer);

    expect(EntityRecord::forEntity($entity)->count())->toBe(2);
});

test('running the same source twice with a unique key updates instead of duplicating', function () {
    $entity = runnerContactsEntity();
    $importer = csvImporterFor($entity, "nome,email\nMario Rossi,mario@example.com\n", uniqueKeyField: 'nome');

    app(ImporterRunner::class)->run($importer);
    expect(EntityRecord::forEntity($entity)->count())->toBe(1);

    // Same unique key ("Mario Rossi"), different email — the record should be updated, not duplicated.
    file_put_contents($importer->config['path_or_url'], "nome,email\nMario Rossi,mario.new@example.com\n");
    app(ImporterRunner::class)->run($importer);

    expect(EntityRecord::forEntity($entity)->count())->toBe(1);
    expect(EntityRecord::forEntity($entity)->first()->email)->toBe('mario.new@example.com');
});

test('a failing row is isolated and does not stop the rest of the run', function () {
    $entity = runnerContactsEntity();

    // A unique index on "nome" turns the second "Duplicate" row into a real
    // failure at the database layer, without needing app-level constraints.
    Schema::table($entity->table_name, function ($table) {
        $table->unique('nome');
    });

    $importer = csvImporterFor($entity, "nome,email\nDuplicate,one@example.com\nDuplicate,two@example.com\nUnica,three@example.com\n");

    $run = app(ImporterRunner::class)->run($importer);

    expect($run->status)->toBe(ImporterRunStatus::PartialFailure);
    expect($run->rows_imported)->toBe(2);
    expect($run->rows_failed)->toBe(1);
    expect($run->error_message)->not->toBeNull();
    expect(EntityRecord::forEntity($entity)->count())->toBe(2);
});

test('a run whose channel itself fails is marked as failed', function () {
    $entity = runnerContactsEntity();

    $importer = Importer::create([
        'title' => 'Import rotto', 'entity_id' => $entity->id, 'slug' => 'import-rotto-'.uniqid(),
        'channel' => 'csv', 'config' => ['path_or_url' => '/no/such/file.csv'],
        'field_mapping' => ['nome' => 'nome'], 'schedule_type' => 'manual',
    ]);

    $run = app(ImporterRunner::class)->run($importer);

    expect($run->status)->toBe(ImporterRunStatus::Failed);
    expect($run->rows_imported)->toBe(0);
    expect($run->error_message)->not->toBeNull();
});
