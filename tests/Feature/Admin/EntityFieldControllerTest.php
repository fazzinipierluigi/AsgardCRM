<?php

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityCard;
use App\Models\EntityTab;
use App\Models\Importer;
use App\Models\WorkflowNode;
use App\Services\EntityInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function installedEntityWithCard(): array
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    return [$entity->fresh(), $card];
}

test('guests are redirected to login', function () {
    [$entity] = installedEntityWithCard();

    $this->get(route('admin.entities.fields.create', $entity))->assertRedirect(route('login'));
});

test('admin can view the add-field form for an installed entity', function () {
    [$entity] = installedEntityWithCard();

    $this->actingAs(adminUser())->get(route('admin.entities.fields.create', $entity))->assertOk();
});

test('adding a field appends a real column to the entity table', function () {
    [$entity, $card] = installedEntityWithCard();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Cognome',
        'column_name' => 'cognome',
        'type' => 'string',
    ]);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));
    expect(Schema::hasColumn('entity_contatti', 'cognome'))->toBeTrue();

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'cognome');
    expect($field)->not->toBeNull();
    expect($field->is_locked)->toBeFalse();
});

test('a select field stores its parsed options', function () {
    [$entity, $card] = installedEntityWithCard();

    $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Stato',
        'column_name' => 'stato',
        'type' => 'select',
        'options' => "open:Aperto\nclosed:Chiuso",
    ]);

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'stato');
    expect($field->options)->toBe(['open' => 'Aperto', 'closed' => 'Chiuso']);
});

test('a relation field adds an _id column', function () {
    [$entity, $card] = installedEntityWithCard();

    $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Responsabile',
        'column_name' => 'responsabile',
        'type' => 'relation',
        'relation_target' => 'model:App\\Models\\User',
    ]);

    expect(Schema::hasColumn('entity_contatti', 'responsabile_id'))->toBeTrue();
});

test('cannot add a field to an entity that is not installed', function () {
    $entity = Entity::create(['name' => 'Bozza', 'slug' => 'bozza', 'table_name' => 'entity_bozza']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Card', 'position' => 0]);

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Nome',
        'column_name' => 'nome',
        'type' => 'string',
    ]);

    $response->assertSessionHasErrors('entity_card_id');
    expect($entity->fresh()->allFields())->toHaveCount(0);
});

test('a reserved column name is rejected', function () {
    [$entity, $card] = installedEntityWithCard();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Utente',
        'column_name' => 'user_id',
        'type' => 'string',
    ]);

    $response->assertSessionHasErrors('column_name');
});

test('a duplicate column name is rejected', function () {
    [$entity, $card] = installedEntityWithCard();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Nome duplicato',
        'column_name' => 'nome',
        'type' => 'string',
    ]);

    $response->assertSessionHasErrors('column_name');
});

test('a select field without options is rejected', function () {
    [$entity, $card] = installedEntityWithCard();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Stato',
        'column_name' => 'stato',
        'type' => 'select',
    ]);

    $response->assertSessionHasErrors('options');
});

test('a button field with a workflow action stores its config and adds no column', function () {
    [$entity, $card] = installedEntityWithCard();
    $workflow = wfWorkflowWithVersion();
    WorkflowNode::factory()->for($workflow->currentVersion)->start()->create();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Avvia flusso',
        'column_name' => 'avvia_flusso',
        'type' => 'button',
        'button_action' => 'workflow',
        'button_workflow_id' => $workflow->id,
    ]);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'avvia_flusso');
    expect($field->options)->toBe([
        'button_action' => 'workflow',
        'button_workflow_id' => $workflow->id,
        'button_importer_ids' => [],
        'button_javascript' => null,
    ]);
    expect(Schema::hasColumn('entity_contatti', 'avvia_flusso'))->toBeFalse();
});

test('a button field with an importer action stores its config', function () {
    [$entity, $card] = installedEntityWithCard();
    $importer = Importer::create([
        'title' => 'Import test',
        'entity_id' => $entity->id,
        'slug' => 'import-test-'.uniqid(),
        'channel' => 'csv',
        'config' => ['path_or_url' => '/tmp/x.csv'],
        'field_mapping' => ['nome' => 'nome'],
        'schedule_type' => 'manual',
    ]);

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Importa',
        'column_name' => 'importa',
        'type' => 'button',
        'button_action' => 'importer',
        'button_importer_ids' => [$importer->id],
    ]);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'importa');
    expect($field->options)->toBe([
        'button_action' => 'importer',
        'button_workflow_id' => null,
        'button_importer_ids' => [$importer->id],
        'button_javascript' => null,
    ]);
    expect(Schema::hasColumn('entity_contatti', 'importa'))->toBeFalse();
});

test('a button field with a javascript action stores its code', function () {
    [$entity, $card] = installedEntityWithCard();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Esegui script',
        'column_name' => 'esegui_script',
        'type' => 'button',
        'button_action' => 'javascript',
        'button_javascript' => "alert('ciao')",
    ]);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'esegui_script');
    expect($field->options)->toBe([
        'button_action' => 'javascript',
        'button_workflow_id' => null,
        'button_importer_ids' => [],
        'button_javascript' => "alert('ciao')",
    ]);
});

test('a button field requires the config for its chosen action', function () {
    [$entity, $card] = installedEntityWithCard();

    $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Avvia flusso',
        'column_name' => 'avvia_flusso',
        'type' => 'button',
        'button_action' => 'workflow',
    ])->assertSessionHasErrors('button_workflow_id');

    $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Importa',
        'column_name' => 'importa',
        'type' => 'button',
        'button_action' => 'importer',
    ])->assertSessionHasErrors('button_importer_ids');

    $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Esegui script',
        'column_name' => 'esegui_script',
        'type' => 'button',
        'button_action' => 'javascript',
    ])->assertSessionHasErrors('button_javascript');
});

test('a table field stores its parsed column definitions and adds a json column', function () {
    [$entity, $card] = installedEntityWithCard();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Righe ordine',
        'column_name' => 'righe_ordine',
        'type' => 'table',
        'table_columns' => "quantita:Quantità:integer:si\nnote:Note:string:no",
    ]);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'righe_ordine');
    expect($field->options)->toBe([
        'columns' => [
            ['name' => 'quantita', 'label' => 'Quantità', 'type' => 'integer', 'required' => true],
            ['name' => 'note', 'label' => 'Note', 'type' => 'string', 'required' => false],
        ],
    ]);
    expect(Schema::hasColumn('entity_contatti', 'righe_ordine'))->toBeTrue();
});

test('a table field without any column definition is rejected', function () {
    [$entity, $card] = installedEntityWithCard();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Righe ordine',
        'column_name' => 'righe_ordine',
        'type' => 'table',
        'table_columns' => '',
    ]);

    $response->assertSessionHasErrors('table_columns');
});

test('a button field cannot target a non-manual workflow', function () {
    [$entity, $card] = installedEntityWithCard();
    $workflow = wfWorkflowWithVersion();
    WorkflowNode::factory()->for($workflow->currentVersion)->create(['type' => 'start', 'config' => ['trigger_type' => 'entity_created', 'entity_slug' => 'contatti']]);

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Avvia flusso',
        'column_name' => 'avvia_flusso',
        'type' => 'button',
        'button_action' => 'workflow',
        'button_workflow_id' => $workflow->id,
    ]);

    $response->assertSessionHasErrors('button_workflow_id');
});
