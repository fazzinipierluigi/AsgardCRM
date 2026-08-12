<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Jobs\RunImporterJob;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use Fazzinipierluigi\CrmCore\Models\Importer;
use Fazzinipierluigi\CrmCore\Models\WorkflowInstance;
use Fazzinipierluigi\CrmCore\Models\WorkflowNode;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Fazzinipierluigi\CrmCore\Tests\Fixtures\User;

uses(RefreshDatabase::class);

function publicWidgetTestEntity(): Entity
{
    $entity = Entity::create(['name' => 'Ordini', 'slug' => 'ordini-public-widget', 'table_name' => 'entity_ordini_public_widget']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Stato', 'column_name' => 'stato', 'type' => EntityFieldType::Select, 'options' => ['aperto' => 'Aperto', 'chiuso' => 'Chiuso'], 'position' => 0]);
    $card->fields()->create(['name' => 'Importo', 'column_name' => 'importo', 'type' => EntityFieldType::DecimalNumber, 'position' => 1]);

    app(EntityInstaller::class)->install($entity);

    return $entity->fresh();
}

test('a counter widget returns the filtered record count', function () {
    $entity = publicWidgetTestEntity();
    $user = adminUser();
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'stato' => 'aperto', 'importo' => 10]);
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'stato' => 'aperto', 'importo' => 20]);
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'stato' => 'chiuso', 'importo' => 5]);

    $widget = $entity->listWidgets()->create([
        'type' => 'counter',
        'name' => 'Aperti',
        'config' => ['filter' => ['column' => 'stato', 'operator' => '=', 'value' => 'aperto'], 'color' => 'primary', 'icon' => null],
        'position' => 0,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->getJson(route('entities.widgets.data', [$entity, $widget]));

    $response->assertOk()->assertJson(['value' => 2]);
});

test('a chart widget returns labels resolved from select options and aggregated values', function () {
    $entity = publicWidgetTestEntity();
    $user = adminUser();
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'stato' => 'aperto', 'importo' => 10]);
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'stato' => 'aperto', 'importo' => 20]);
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'stato' => 'chiuso', 'importo' => 5]);

    $widget = $entity->listWidgets()->create([
        'type' => 'chart',
        'name' => 'Importo per stato',
        'config' => ['chart_type' => 'bar', 'group_by' => 'stato', 'aggregate' => 'sum', 'value_column' => 'importo', 'filter' => null],
        'position' => 0,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->getJson(route('entities.widgets.data', [$entity, $widget]));

    $response->assertOk();
    $data = collect($response->json('labels'))->combine($response->json('values'));
    expect((float) $data['Aperto'])->toBe(30.0);
    expect((float) $data['Chiuso'])->toBe(5.0);
});

test('clicking a workflow button widget starts the workflow without binding a record', function () {
    $entity = publicWidgetTestEntity();
    $workflow = wfWorkflowWithVersion();
    WorkflowNode::factory()->for($workflow->currentVersion)->start()->create();

    $widget = $entity->listWidgets()->create([
        'type' => 'button',
        'name' => 'Avvia',
        'config' => ['button_action' => 'workflow', 'button_workflow_id' => $workflow->id, 'button_importer_ids' => [], 'button_javascript' => null],
        'position' => 0,
        'is_active' => true,
    ]);

    $response = $this->actingAs(adminUser())->postJson(route('entities.widgets.trigger', [$entity, $widget]));

    $response->assertOk();
    $instance = WorkflowInstance::where('workflow_id', $workflow->id)->firstOrFail();
    expect($instance->entity_id)->toBeNull();
});

test('clicking an importer button widget dispatches a job per configured importer', function () {
    Queue::fake();
    $entity = publicWidgetTestEntity();
    $importer = Importer::create([
        'title' => 'Import test',
        'entity_id' => $entity->id,
        'slug' => 'import-widget-test-'.uniqid(),
        'channel' => 'csv',
        'config' => ['path_or_url' => '/tmp/x.csv'],
        'field_mapping' => ['stato' => 'stato'],
        'schedule_type' => 'manual',
    ]);

    $widget = $entity->listWidgets()->create([
        'type' => 'button',
        'name' => 'Importa',
        'config' => ['button_action' => 'importer', 'button_workflow_id' => null, 'button_importer_ids' => [$importer->id], 'button_javascript' => null],
        'position' => 0,
        'is_active' => true,
    ]);

    $response = $this->actingAs(adminUser())->postJson(route('entities.widgets.trigger', [$entity, $widget]));

    $response->assertOk();
    Queue::assertPushed(RunImporterJob::class, fn ($job) => $job->importer->is($importer));
});

test('a user without the entity index permission is forbidden', function () {
    $entity = publicWidgetTestEntity();
    $widget = $entity->listWidgets()->create([
        'type' => 'counter',
        'name' => 'Aperti',
        'config' => ['filter' => null, 'color' => 'primary', 'icon' => null],
        'position' => 0,
        'is_active' => true,
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('entities.widgets.data', [$entity, $widget]))->assertForbidden();
});

test('a user with the entity index permission can read widget data', function () {
    $entity = publicWidgetTestEntity();
    $widget = $entity->listWidgets()->create([
        'type' => 'counter',
        'name' => 'Aperti',
        'config' => ['filter' => null, 'color' => 'primary', 'icon' => null],
        'position' => 0,
        'is_active' => true,
    ]);
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);
    $role->givePermission(Permission::where('key', 'entity_ordini-public-widget.index')->firstOrFail());
    $user->assignRole($role);

    $this->actingAs($user)->getJson(route('entities.widgets.data', [$entity, $widget]))->assertOk();
});

test('an inactive widget 404s', function () {
    $entity = publicWidgetTestEntity();
    $widget = $entity->listWidgets()->create([
        'type' => 'counter',
        'name' => 'Aperti',
        'config' => ['filter' => null, 'color' => 'primary', 'icon' => null],
        'position' => 0,
        'is_active' => false,
    ]);

    $this->actingAs(adminUser())->getJson(route('entities.widgets.data', [$entity, $widget]))->assertNotFound();
});
