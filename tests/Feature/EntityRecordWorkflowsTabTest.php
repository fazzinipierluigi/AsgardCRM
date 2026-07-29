<?php

use App\Enums\EntityFieldType;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowNodeType;
use App\Models\Entity;
use App\Models\EntityCard;
use App\Models\EntityRecord;
use App\Models\EntityTab;
use App\Models\User;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Services\EntityInstaller;
use App\Services\Workflows\WorkflowEngine;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function workflowsTabEntity(): Entity
{
    $entity = Entity::create(['name' => 'Preventivi Flussi', 'slug' => 'preventivi-flussi', 'table_name' => 'entity_preventivi_flussi']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Titolo', 'column_name' => 'titolo', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    return $entity;
}

function startBoundInstance(Entity $entity, EntityRecord $record)
{
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ServiceTask]);
    $end = WorkflowNode::factory()->for($version)->end()->create();

    WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => $start->id, 'target_node_id' => $task->id]);
    WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => $task->id, 'target_node_id' => $end->id]);

    return app(WorkflowEngine::class)->start($workflow, [], $record, entitySlug: $entity->slug);
}

test('the Flussi tab is hidden without entity_{slug}.workflows permission', function () {
    $entity = workflowsTabEntity();
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);
    $role->givePermission(Permission::where('key', "entity_{$entity->slug}.edit")->firstOrFail());
    $user->assignRole($role);

    // OwnOnly is the default visibility level, so the record must belong
    // to $user for EntityRecordAuthorizer::canEdit() to allow the edit
    // page at all — unrelated to the permission this test is about.
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'titolo' => 'X']);

    $this->actingAs($user)->get(route('entities.edit', [$entity, $record]))
        ->assertOk()
        ->assertDontSee('data-testid="entity-record-workflows-tab"', false);
});

test('a permitted user sees the Flussi tab and its instance list', function () {
    $entity = workflowsTabEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'titolo' => 'X']);
    $instance = startBoundInstance($entity, $record);

    $response = $this->actingAs($admin)->get(route('entities.edit', [$entity, $record]));

    $response->assertOk()
        ->assertSee('data-testid="entity-record-workflows-tab"', false)
        ->assertSee($instance->workflow->name);
});

test('the workflow instance graph endpoint reports node status and per-node logs', function () {
    $entity = workflowsTabEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'titolo' => 'X']);
    $instance = startBoundInstance($entity, $record);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed);

    $response = $this->actingAs($admin)->getJson(route('entities.workflow-instance-graph', [$entity, $record, $instance]));

    $response->assertOk();
    $payload = $response->json();

    expect($payload['nodes'])->toHaveCount(3);
    expect(collect($payload['nodes'])->pluck('status')->unique()->all())->toBe(['completed']);
    expect(collect($payload['edges'])->pluck('executed')->unique()->all())->toBe([true]);
    expect($payload['logs'])->toHaveCount(3);
});

test('the workflow instance graph endpoint 404s for an instance bound to a different record', function () {
    $entity = workflowsTabEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'titolo' => 'X']);
    $otherRecord = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'titolo' => 'Y']);
    $instance = startBoundInstance($entity, $otherRecord);

    $this->actingAs($admin)->getJson(route('entities.workflow-instance-graph', [$entity, $record, $instance]))
        ->assertNotFound();
});

test('the workflow instance graph endpoint is forbidden without the permission', function () {
    $entity = workflowsTabEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'titolo' => 'X']);
    $instance = startBoundInstance($entity, $record);

    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);
    $role->givePermission(Permission::where('key', "entity_{$entity->slug}.edit")->firstOrFail());
    $user->assignRole($role);

    $this->actingAs($user)->getJson(route('entities.workflow-instance-graph', [$entity, $record, $instance]))
        ->assertForbidden();
});
