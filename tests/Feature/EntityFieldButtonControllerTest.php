<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Jobs\RunImporterJob;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use Fazzinipierluigi\CrmCore\Models\Importer;
use App\Models\User;
use Fazzinipierluigi\CrmCore\Models\Workflow;
use Fazzinipierluigi\CrmCore\Models\WorkflowInstance;
use Fazzinipierluigi\CrmCore\Models\WorkflowNode;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function buttonFieldEntity(array $buttonOptions): array
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);
    $field = $card->fields()->create([
        'name' => 'Bottone',
        'column_name' => 'bottone',
        'type' => EntityFieldType::Button,
        'options' => $buttonOptions,
        'position' => 1,
    ]);

    app(EntityInstaller::class)->install($entity);

    $entity = $entity->fresh();
    $field = $entity->allFields()->firstWhere('column_name', 'bottone');
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => adminUser()->id, 'nome' => 'Mario Rossi']);

    return [$entity, $field, $record];
}

test('guests are redirected to login', function () {
    [$entity, $field, $record] = buttonFieldEntity(['button_action' => 'javascript', 'button_workflow_id' => null, 'button_importer_ids' => [], 'button_javascript' => 'noop()']);

    $this->post(route('entities.fields.trigger', [$entity, $record, $field]))->assertRedirect(route('login'));
});

test('clicking a workflow button starts a manual workflow bound to the record', function () {
    $workflow = wfWorkflowWithVersion();
    WorkflowNode::factory()->for($workflow->currentVersion)->start()->create();

    [$entity, $field, $record] = buttonFieldEntity([
        'button_action' => 'workflow',
        'button_workflow_id' => $workflow->id,
        'button_importer_ids' => [],
        'button_javascript' => null,
    ]);

    $response = $this->actingAs(adminUser())->postJson(route('entities.fields.trigger', [$entity, $record, $field]));

    $response->assertOk()->assertJsonStructure(['message']);
    $instance = WorkflowInstance::where('workflow_id', $workflow->id)->firstOrFail();
    expect($instance->entity_slug)->toBe('contatti');
    expect($instance->entity_id)->toBe($record->id);
});

test('clicking a workflow button fails gracefully for a workflow with no published version', function () {
    $workflow = Workflow::factory()->create();

    [$entity, $field, $record] = buttonFieldEntity([
        'button_action' => 'workflow',
        'button_workflow_id' => $workflow->id,
        'button_importer_ids' => [],
        'button_javascript' => null,
    ]);

    $response = $this->actingAs(adminUser())->postJson(route('entities.fields.trigger', [$entity, $record, $field]));

    $response->assertStatus(422)->assertJsonStructure(['message']);
});

test('clicking an importer button dispatches a job per configured importer', function () {
    Queue::fake();

    $importerEntity = Entity::create(['name' => 'Import Target', 'slug' => 'import-target', 'table_name' => 'entity_import_target']);
    $importer = Importer::create([
        'title' => 'Import test',
        'entity_id' => $importerEntity->id,
        'slug' => 'import-test-'.uniqid(),
        'channel' => 'csv',
        'config' => ['path_or_url' => '/tmp/x.csv'],
        'field_mapping' => ['nome' => 'nome'],
        'schedule_type' => 'manual',
    ]);

    [$entity, $field, $record] = buttonFieldEntity([
        'button_action' => 'importer',
        'button_workflow_id' => null,
        'button_importer_ids' => [$importer->id],
        'button_javascript' => null,
    ]);

    $response = $this->actingAs(adminUser())->postJson(route('entities.fields.trigger', [$entity, $record, $field]));

    $response->assertOk();
    Queue::assertPushed(RunImporterJob::class, fn ($job) => $job->importer->is($importer));
});

test('a user without the entity edit permission is forbidden', function () {
    [$entity, $field, $record] = buttonFieldEntity(['button_action' => 'javascript', 'button_workflow_id' => null, 'button_importer_ids' => [], 'button_javascript' => 'noop()']);
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);
    $role->givePermission(Permission::where('key', 'entity_contatti.index')->firstOrFail());
    $user->assignRole($role);

    $this->actingAs($user)->postJson(route('entities.fields.trigger', [$entity, $record, $field]))->assertForbidden();
});

test('a field that is not of type button 404s', function () {
    [$entity, , $record] = buttonFieldEntity(['button_action' => 'javascript', 'button_workflow_id' => null, 'button_importer_ids' => [], 'button_javascript' => 'noop()']);
    $nameField = $entity->allFields()->firstWhere('column_name', 'nome');

    $this->actingAs(adminUser())->postJson(route('entities.fields.trigger', [$entity, $record, $nameField]))->assertNotFound();
});
