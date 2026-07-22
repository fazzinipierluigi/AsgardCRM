<?php

use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowNodeType;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowNode;
use App\Services\Workflows\WorkflowEngine;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function wfTaskWorkflow(array $userTaskConfig = []): Workflow
{
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::UserTask,
        'config' => array_merge(['form_fields' => [['name' => 'note', 'label' => 'Note', 'type' => 'string', 'bind_variable' => 'note']]], $userTaskConfig),
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();

    WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => $start->id, 'target_node_id' => $task->id]);
    WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => $task->id, 'target_node_id' => $end->id]);

    return $workflow;
}

test('a user cannot open a task assigned to someone else', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $workflow = wfTaskWorkflow(['assigned_user_id' => $owner->id]);
    $instance = app(WorkflowEngine::class)->start($workflow);
    $task = $instance->userTasks()->first();
    $task->update(['assigned_user_id' => $owner->id]);

    $this->actingAs($stranger)->get(route('workflow-tasks.edit', $task))->assertForbidden();
});

test('the assigned user can complete their task and the workflow resumes', function () {
    $user = User::factory()->create();
    $workflow = wfTaskWorkflow(['assigned_user_id' => null]);
    $instance = app(WorkflowEngine::class)->start($workflow);
    $task = $instance->userTasks()->first();

    $this->actingAs($user)->get(route('workflow-tasks.edit', $task))->assertOk();

    $this->actingAs($user)->put(route('workflow-tasks.update', $task), ['note' => 'Tutto ok'])
        ->assertRedirect(route('workflow-tasks.index'));

    $instance->refresh();
    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->getVariable('note'))->toBe('Tutto ok');
});

test('a user holding the assigned role can complete the task', function () {
    $role = Role::create(['name' => 'Approvatori', 'slug' => 'approvatori']);
    $user = User::factory()->create();
    $user->assignRole($role);

    $workflow = wfTaskWorkflow();
    $instance = app(WorkflowEngine::class)->start($workflow);
    $task = $instance->userTasks()->first();
    $task->update(['assigned_role_id' => $role->id]);

    $this->actingAs($user)->put(route('workflow-tasks.update', $task), ['note' => 'Approvato'])
        ->assertRedirect(route('workflow-tasks.index'));

    expect($instance->fresh()->status)->toBe(WorkflowInstanceStatus::Completed);
});
