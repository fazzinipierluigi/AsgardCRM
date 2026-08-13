<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionPhase;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowInstanceStatus;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowNodeType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Models\Workflow;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowEdge;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowInstance;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function wfTriggerEntity(): Entity
{
    $entity = Entity::create(['name' => 'Ordine WF', 'slug' => 'ordine-wf-'.uniqid(), 'table_name' => 'entity_ordine_wf_'.uniqid()]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Totale', 'column_name' => 'totale', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    return $entity;
}

function wfTriggerWorkflow(Entity $entity, string $triggerType, string $occurrence = 'every_time'): Workflow
{
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create([
        'config' => ['trigger_type' => $triggerType, 'entity_slug' => $entity->slug, 'occurrence' => $occurrence],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => $start->id, 'target_node_id' => $end->id]);

    return $workflow;
}

test('creating an entity record starts a workflow bound to it as the system entity', function () {
    $entity = wfTriggerEntity();
    $workflow = wfTriggerWorkflow($entity, 'entity_created');
    $userId = User::factory()->create()->id;

    $record = EntityRecord::forEntity($entity)->create(['totale' => '100', 'user_id' => $userId]);

    $instance = WorkflowInstance::where('workflow_id', $workflow->id)->first();

    expect($instance)->not->toBeNull()
        ->and($instance->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->entity_id)->toBe($record->id);
});

test('updating an entity record does not start a workflow that only triggers on creation', function () {
    $entity = wfTriggerEntity();
    wfTriggerWorkflow($entity, 'entity_created');
    $userId = User::factory()->create()->id;

    $record = EntityRecord::forEntity($entity)->create(['totale' => '100', 'user_id' => $userId]);
    WorkflowInstance::query()->delete();

    $record->update(['totale' => '200']);

    expect(WorkflowInstance::count())->toBe(0);
});

test('updating an entity record starts a workflow that triggers on update', function () {
    $entity = wfTriggerEntity();
    $workflow = wfTriggerWorkflow($entity, 'entity_updated');
    $userId = User::factory()->create()->id;

    $record = EntityRecord::forEntity($entity)->create(['totale' => '100', 'user_id' => $userId]);
    $record->update(['totale' => '200']);

    expect(WorkflowInstance::where('workflow_id', $workflow->id)->exists())->toBeTrue();
});

test('an entity-triggered instance can read the triggering record fields via the entity context', function () {
    $entity = wfTriggerEntity();
    $workflow = wfTriggerWorkflow($entity, 'entity_created');
    $version = $workflow->currentVersion;
    $node = $version->nodes()->where('type', WorkflowNodeType::End->value)->first();
    $node->actions()->create([
        'workflow_version_id' => $version->id,
        'phase' => WorkflowActionPhase::Before,
        'type' => WorkflowActionType::SetVariable,
        'config' => ['variable' => 'totale_letto', 'expression' => 'entity.totale'],
    ]);
    $version->variables()->create(['name' => 'totale_letto', 'type' => 'string']);
    $userId = User::factory()->create()->id;

    $record = EntityRecord::forEntity($entity)->create(['totale' => '350', 'user_id' => $userId]);

    $instance = WorkflowInstance::where('workflow_id', $workflow->id)->first();

    expect($instance->entity_slug)->toBe($entity->slug)
        ->and($instance->resolveEntity()->id)->toBe($record->id)
        ->and($instance->getVariable('totale_letto'))->toBe('350');
});

test('an update trigger freezes the pre-update field values as the __entity_previous system variable', function () {
    $entity = wfTriggerEntity();
    $workflow = wfTriggerWorkflow($entity, 'entity_updated');
    $userId = User::factory()->create()->id;

    $record = EntityRecord::forEntity($entity)->create(['totale' => '100', 'user_id' => $userId]);
    $record->update(['totale' => '200']);

    $instance = WorkflowInstance::where('workflow_id', $workflow->id)->firstOrFail();

    expect($instance->getVariable('__entity_previous.totale'))->toBe('100')
        ->and($instance->resolveEntity()->totale)->toBe('200');
});

test('a create trigger has no previous values, so __entity_previous is empty', function () {
    $entity = wfTriggerEntity();
    $workflow = wfTriggerWorkflow($entity, 'entity_created');
    $userId = User::factory()->create()->id;

    EntityRecord::forEntity($entity)->create(['totale' => '100', 'user_id' => $userId]);

    $instance = WorkflowInstance::where('workflow_id', $workflow->id)->firstOrFail();

    expect($instance->getVariable('__entity_previous'))->toBe([]);
});

test('occurrence "every_time" (default) starts again on every subsequent update', function () {
    $entity = wfTriggerEntity();
    $workflow = wfTriggerWorkflow($entity, 'entity_updated', 'every_time');
    $userId = User::factory()->create()->id;

    $record = EntityRecord::forEntity($entity)->create(['totale' => '100', 'user_id' => $userId]);
    $record->update(['totale' => '200']);
    $record->update(['totale' => '300']);

    expect(WorkflowInstance::where('workflow_id', $workflow->id)->count())->toBe(2);
});

test('occurrence "once" only starts the workflow the first time for a given record', function () {
    $entity = wfTriggerEntity();
    $workflow = wfTriggerWorkflow($entity, 'entity_updated', 'once');
    $userId = User::factory()->create()->id;

    $record = EntityRecord::forEntity($entity)->create(['totale' => '100', 'user_id' => $userId]);
    $record->update(['totale' => '200']);
    $record->update(['totale' => '300']);

    expect(WorkflowInstance::where('workflow_id', $workflow->id)->count())->toBe(1);
});

test('occurrence "once" still lets a different record start its own instance', function () {
    $entity = wfTriggerEntity();
    $workflow = wfTriggerWorkflow($entity, 'entity_updated', 'once');
    $userId = User::factory()->create()->id;

    $recordA = EntityRecord::forEntity($entity)->create(['totale' => '100', 'user_id' => $userId]);
    $recordA->update(['totale' => '150']);
    $recordB = EntityRecord::forEntity($entity)->create(['totale' => '200', 'user_id' => $userId]);
    $recordB->update(['totale' => '250']);

    expect(WorkflowInstance::where('workflow_id', $workflow->id)->count())->toBe(2);
});
