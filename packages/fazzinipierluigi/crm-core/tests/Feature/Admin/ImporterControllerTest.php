<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use Fazzinipierluigi\CrmCore\Models\Importer;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function importerContactsEntity(bool $installed = true): Entity
{
    $entity = Entity::create(['name' => 'Contatti Importer', 'slug' => 'contatti-importer-'.uniqid(), 'table_name' => 'entity_contatti_importer_'.uniqid()]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'required' => true, 'position' => 0]);
    $card->fields()->create(['name' => 'Email', 'column_name' => 'email', 'type' => EntityFieldType::String, 'position' => 1]);

    if ($installed) {
        app(EntityInstaller::class)->install($entity);
    }

    return $entity;
}

/**
 * @return array<string, mixed>
 */
function baseImporterPayload(Entity $entity): array
{
    return [
        'title' => 'Import contatti CSV',
        'description' => 'Importa i contatti da un file CSV',
        'entity_id' => $entity->id,
        'channel' => 'csv',
        'path_or_url' => '/tmp/does-not-need-to-exist-for-validation.csv',
        'delimiter' => ',',
        'has_header' => '1',
        'field_mapping_json' => json_encode(['nome' => 'nome', 'email' => 'email']),
        'unique_key_field' => '',
        'schedule_type' => 'manual',
        'is_active' => '1',
    ];
}

test('guests are redirected to login', function () {
    $this->get(route('admin.importers.index'))->assertRedirect(route('login'));
});

test('admin can view the importers index create and edit pages', function () {
    $entity = importerContactsEntity();
    $importer = Importer::create([
        'title' => 'Import esistente', 'entity_id' => $entity->id, 'slug' => 'import-esistente',
        'channel' => 'csv', 'config' => ['path_or_url' => '/tmp/x.csv'],
        'field_mapping' => ['nome' => 'nome'], 'schedule_type' => 'manual',
    ]);

    $admin = adminUser();
    $this->actingAs($admin)->get(route('admin.importers.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.importers.create'))->assertOk();
    $this->actingAs($admin)->get(route('admin.importers.edit', $importer))->assertOk();
    $this->actingAs($admin)->get(route('admin.importers.show', $importer))->assertOk();
});

test('admin can create a csv importer', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.importers.store'), baseImporterPayload($entity));

    $response->assertRedirect(route('admin.importers.index'));

    $importer = Importer::where('title', 'Import contatti CSV')->firstOrFail();
    expect($importer->slug)->toBe('import-contatti-csv');
    expect($importer->channel->value)->toBe('csv');
    expect($importer->config['path_or_url'])->toBe('/tmp/does-not-need-to-exist-for-validation.csv');
    expect($importer->field_mapping)->toBe(['nome' => 'nome', 'email' => 'email']);
    expect($importer->unique_key_field)->toBeNull();
    expect($importer->schedule_type->value)->toBe('manual');
});

test('csv channel requires path_or_url', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $payload = baseImporterPayload($entity);
    unset($payload['path_or_url']);

    $this->actingAs($admin)->post(route('admin.importers.store'), $payload)
        ->assertSessionHasErrors(['path_or_url']);
});

test('database channel requires connection fields', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $payload = baseImporterPayload($entity);
    $payload['channel'] = 'database';
    unset($payload['path_or_url'], $payload['delimiter'], $payload['has_header']);

    $this->actingAs($admin)->post(route('admin.importers.store'), $payload)
        ->assertSessionHasErrors(['driver', 'host', 'database', 'username', 'password', 'query']);
});

test('admin can create a database importer', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $payload = baseImporterPayload($entity);
    unset($payload['path_or_url'], $payload['delimiter'], $payload['has_header']);
    $payload['channel'] = 'database';
    $payload['driver'] = 'mysql';
    $payload['host'] = 'db.example.com';
    $payload['port'] = 3306;
    $payload['database'] = 'legacy';
    $payload['username'] = 'reader';
    $payload['password'] = 'secret';
    $payload['query'] = 'SELECT * FROM contacts';

    $this->actingAs($admin)->post(route('admin.importers.store'), $payload)
        ->assertRedirect(route('admin.importers.index'));

    $importer = Importer::where('title', 'Import contatti CSV')->firstOrFail();
    expect($importer->config['host'])->toBe('db.example.com');
    expect($importer->config['password'])->toBe('secret');
});

test('rest api channel requires method and endpoint', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $payload = baseImporterPayload($entity);
    unset($payload['path_or_url'], $payload['delimiter'], $payload['has_header']);
    $payload['channel'] = 'rest_api';

    $this->actingAs($admin)->post(route('admin.importers.store'), $payload)
        ->assertSessionHasErrors(['method', 'endpoint']);
});

test('field mapping must target valid entity columns', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $payload = baseImporterPayload($entity);
    $payload['field_mapping_json'] = json_encode(['nome' => 'campo_inesistente']);

    $this->actingAs($admin)->post(route('admin.importers.store'), $payload)
        ->assertSessionHasErrors(['field_mapping_json']);
});

test('field mapping cannot be empty', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $payload = baseImporterPayload($entity);
    $payload['field_mapping_json'] = json_encode([]);

    $this->actingAs($admin)->post(route('admin.importers.store'), $payload)
        ->assertSessionHasErrors(['field_mapping_json']);
});

test('unique key field must be among the mapped values', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $payload = baseImporterPayload($entity);
    $payload['unique_key_field'] = 'email_non_mappata';

    $this->actingAs($admin)->post(route('admin.importers.store'), $payload)
        ->assertSessionHasErrors(['unique_key_field']);
});

test('cron expression is required when schedule type is cron', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $payload = baseImporterPayload($entity);
    $payload['schedule_type'] = 'cron';

    $this->actingAs($admin)->post(route('admin.importers.store'), $payload)
        ->assertSessionHasErrors(['cron_expression']);
});

test('an invalid cron expression is rejected', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $payload = baseImporterPayload($entity);
    $payload['schedule_type'] = 'cron';
    $payload['cron_expression'] = 'not-a-cron-expression';

    $this->actingAs($admin)->post(route('admin.importers.store'), $payload)
        ->assertSessionHasErrors(['cron_expression']);
});

test('a valid cron expression is accepted', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $payload = baseImporterPayload($entity);
    $payload['schedule_type'] = 'both';
    $payload['cron_expression'] = '*/15 * * * *';

    $this->actingAs($admin)->post(route('admin.importers.store'), $payload)
        ->assertRedirect(route('admin.importers.index'));

    expect(Importer::where('title', 'Import contatti CSV')->firstOrFail()->cron_expression)->toBe('*/15 * * * *');
});

test('only installed entities can be selected', function () {
    $entity = importerContactsEntity(installed: false);
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.importers.store'), baseImporterPayload($entity))
        ->assertSessionHasErrors(['entity_id']);
});

test('entity_id and channel cannot be changed on update', function () {
    $entity = importerContactsEntity();
    $otherEntity = importerContactsEntity();
    $admin = adminUser();

    $importer = Importer::create([
        'title' => 'Import originale', 'entity_id' => $entity->id, 'slug' => 'import-originale',
        'channel' => 'csv', 'config' => ['path_or_url' => '/tmp/original.csv'],
        'field_mapping' => ['nome' => 'nome'], 'schedule_type' => 'manual',
    ]);

    $payload = baseImporterPayload($entity);
    $payload['title'] = 'Import rinominato';
    $payload['entity_id'] = $otherEntity->id;
    $payload['channel'] = 'json';

    $this->actingAs($admin)->put(route('admin.importers.update', $importer), $payload)
        ->assertRedirect(route('admin.importers.index'));

    $importer->refresh();
    expect($importer->title)->toBe('Import rinominato');
    expect($importer->entity_id)->toBe($entity->id);
    expect($importer->channel->value)->toBe('csv');
});

test('leaving a secret blank on update keeps the previous value', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $importer = Importer::create([
        'title' => 'Import DB', 'entity_id' => $entity->id, 'slug' => 'import-db',
        'channel' => 'database',
        'config' => ['driver' => 'mysql', 'host' => 'db.example.com', 'port' => 3306, 'database' => 'legacy', 'username' => 'reader', 'password' => 'original-secret', 'query' => 'SELECT 1'],
        'field_mapping' => ['nome' => 'nome'], 'schedule_type' => 'manual',
    ]);

    $payload = [
        'title' => 'Import DB', 'description' => '', 'entity_id' => $entity->id, 'channel' => 'database',
        'driver' => 'mysql', 'host' => 'db.example.com', 'port' => 3306, 'database' => 'legacy', 'username' => 'reader',
        'password' => '', 'query' => 'SELECT 1',
        'field_mapping_json' => json_encode(['nome' => 'nome']), 'unique_key_field' => '',
        'schedule_type' => 'manual', 'is_active' => '1',
    ];

    $this->actingAs($admin)->put(route('admin.importers.update', $importer), $payload)
        ->assertRedirect(route('admin.importers.index'));

    expect($importer->refresh()->config['password'])->toBe('original-secret');
});

test('admin can delete an importer', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    $importer = Importer::create([
        'title' => 'Da eliminare', 'entity_id' => $entity->id, 'slug' => 'da-eliminare',
        'channel' => 'csv', 'config' => ['path_or_url' => '/tmp/x.csv'],
        'field_mapping' => ['nome' => 'nome'], 'schedule_type' => 'manual',
    ]);

    $this->actingAs($admin)->delete(route('admin.importers.destroy', $importer))
        ->assertRedirect(route('admin.importers.index'));

    expect(Importer::find($importer->id))->toBeNull();
});

test('the data endpoint returns importers as json', function () {
    $entity = importerContactsEntity();
    $admin = adminUser();

    Importer::create([
        'title' => 'Import per grid', 'entity_id' => $entity->id, 'slug' => 'import-per-grid',
        'channel' => 'csv', 'config' => ['path_or_url' => '/tmp/x.csv'],
        'field_mapping' => ['nome' => 'nome'], 'schedule_type' => 'manual',
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.importers.data', ['start' => 0, 'limit' => 25]));

    $response->assertOk();
    $response->assertJsonFragment(['title' => 'Import per grid', 'entity' => $entity->name]);
});
