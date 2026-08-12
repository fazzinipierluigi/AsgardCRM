<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Enums\WorkflowNodeType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use App\Models\User;
use Fazzinipierluigi\CrmCore\Models\WorkflowEdge;
use Fazzinipierluigi\CrmCore\Models\WorkflowNode;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Fazzinipierluigi\CrmCore\Services\Workflows\WorkflowEngine;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ertOrdiniEntity(): Entity
{
    $entity = Entity::create(['name' => 'Ordini WF', 'slug' => 'ordini-wf-'.uniqid(), 'table_name' => 'entity_ordini_wf_'.uniqid()]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Totale', 'column_name' => 'totale', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    return $entity;
}

test('a pending user task flagged show_in_entity_detail appears on the record edit page', function () {
    $entity = ertOrdiniEntity();
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::UserTask,
        'name' => 'Verifica ordine',
        'config' => ['show_in_entity_detail' => true],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => $start->id, 'target_node_id' => $task->id]);
    WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => $task->id, 'target_node_id' => $end->id]);

    $recordUser = User::factory()->create();
    $record = EntityRecord::forEntity($entity)->create(['totale' => '100', 'user_id' => $recordUser->id]);

    app(WorkflowEngine::class)->start($workflow, [], $record, entitySlug: $entity->slug);

    $role = Role::create(['name' => 'Operatore Ordini', 'slug' => 'operatore-ordini-'.uniqid()]);
    $role->givePermission(Permission::where('key', "entity_{$entity->slug}.edit")->firstOrFail());
    $recordUser->assignRole($role);

    $response = $this->actingAs($recordUser)->get(route('entities.edit', [$entity, $record]));

    $response->assertOk()->assertSee('Verifica ordine');
});
