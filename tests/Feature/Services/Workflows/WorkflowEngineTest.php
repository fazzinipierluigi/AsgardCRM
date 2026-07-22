<?php

use App\Enums\WorkflowActionPhase;
use App\Enums\WorkflowActionType;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowTimerStatus;
use App\Enums\WorkflowTokenStatus;
use App\Enums\WorkflowUserTaskStatus;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNode;
use App\Services\Workflows\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Wires two nodes together with a plain, unconditional edge.
 *
 * @param  mixed  $conditionLogic  A JsonLogic rule, or null.
 */
function wfConnect(WorkflowNode $from, WorkflowNode $to, mixed $conditionLogic = null, int $sequence = 0): WorkflowEdge
{
    return WorkflowEdge::create([
        'workflow_version_id' => $from->workflow_version_id,
        'source_node_id' => $from->id,
        'target_node_id' => $to->id,
        'sequence' => $sequence,
        'condition_logic' => $conditionLogic,
    ]);
}

test('a linear workflow runs start to end and applies a set_variable action', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ServiceTask]);
    $end = WorkflowNode::factory()->for($version)->end()->create();

    wfConnect($start, $task);
    wfConnect($task, $end);

    $task->actions()->create([
        'workflow_version_id' => $version->id,
        'phase' => WorkflowActionPhase::After,
        'sequence' => 0,
        'type' => WorkflowActionType::SetVariable,
        'config' => ['variable' => 'saluto', 'expression' => "'ciao ' ~ 'mondo'"],
    ]);

    $instance = app(WorkflowEngine::class)->start($workflow);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->getVariable('saluto'))->toBe('ciao mondo')
        ->and($instance->workflow_version_id)->toBe($version->id)
        ->and($instance->tokens()->count())->toBe(1)
        ->and($instance->tokens()->first()->status)->toBe(WorkflowTokenStatus::Completed);
});

test('an exclusive gate follows the first edge whose JsonLogic condition is true', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $gate = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ExclusiveGateway]);
    $endA = WorkflowNode::factory()->for($version)->end()->create(['name' => 'Fine A']);
    $endB = WorkflowNode::factory()->for($version)->end()->create(['name' => 'Fine B']);

    wfConnect($start, $gate);
    wfConnect($gate, $endA, ['>' => [['var' => 'importo'], 100]], 0);
    wfConnect($gate, $endB, ['<=' => [['var' => 'importo'], 100]], 1);

    $instance = app(WorkflowEngine::class)->start($workflow, ['importo' => 50]);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->tokens()->first()->workflow_node_id)->toBe($endB->id);
});

test('an exclusive gate with no matching condition fails the instance', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $gate = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ExclusiveGateway]);
    $end = WorkflowNode::factory()->for($version)->end()->create();

    wfConnect($start, $gate);
    wfConnect($gate, $end, ['>' => [['var' => 'importo'], 100]]);

    $instance = app(WorkflowEngine::class)->start($workflow, ['importo' => 10]);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Failed)
        ->and($instance->error_message)->toContain('gate esclusivo');
});

test('a parallel gate splits into branches that join at a semaphore', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $split = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ParallelGateway]);
    $branchA = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ServiceTask, 'name' => 'Ramo A']);
    $branchB = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ServiceTask, 'name' => 'Ramo B']);
    $join = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::Semaphore]);
    $end = WorkflowNode::factory()->for($version)->end()->create();

    wfConnect($start, $split);
    wfConnect($split, $branchA, sequence: 0);
    wfConnect($split, $branchB, sequence: 1);
    wfConnect($branchA, $join);
    wfConnect($branchB, $join);
    wfConnect($join, $end);

    $branchA->actions()->create([
        'workflow_version_id' => $version->id, 'phase' => WorkflowActionPhase::After, 'type' => WorkflowActionType::SetVariable,
        'config' => ['variable' => 'a', 'expression' => 'true'],
    ]);
    $branchB->actions()->create([
        'workflow_version_id' => $version->id, 'phase' => WorkflowActionPhase::After, 'type' => WorkflowActionType::SetVariable,
        'config' => ['variable' => 'b', 'expression' => 'true'],
    ]);

    $instance = app(WorkflowEngine::class)->start($workflow);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->getVariable('a'))->toBeTrue()
        ->and($instance->getVariable('b'))->toBeTrue()
        ->and($instance->tokens()->where('status', WorkflowTokenStatus::Cancelled->value)->count())->toBe(1)
        ->and($instance->tokens()->where('status', WorkflowTokenStatus::Completed->value)->count())->toBe(1);
});

test('a user task blocks the instance until completed, then resumes and binds the answer to a variable', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::UserTask,
        'config' => ['form_fields' => [['name' => 'approvato', 'bind_variable' => 'approvato']]],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();

    wfConnect($start, $task);
    wfConnect($task, $end);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start($workflow);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Running)
        ->and($instance->tokens()->first()->status)->toBe(WorkflowTokenStatus::WaitingUserTask)
        ->and($instance->userTasks()->where('status', WorkflowUserTaskStatus::Pending->value)->count())->toBe(1);

    $userTask = $instance->userTasks()->first();
    $engine->completeUserTask($userTask, ['approvato' => true]);

    $instance->refresh();
    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->getVariable('approvato'))->toBeTrue()
        ->and($userTask->fresh()->status)->toBe(WorkflowUserTaskStatus::Completed);
});

test('a timer blocks the instance until fired', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $timer = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::Timer,
        'config' => ['reference' => 'fixed', 'date' => now()->toDateTimeString(), 'direction' => 'after', 'amount' => 15, 'unit' => 'minutes'],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();

    wfConnect($start, $timer);
    wfConnect($timer, $end);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start($workflow);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Running)
        ->and($instance->tokens()->first()->status)->toBe(WorkflowTokenStatus::WaitingTimer);

    $timerRow = $instance->timers()->where('status', WorkflowTimerStatus::Pending->value)->first();
    expect($timerRow->run_at->diffInMinutes(now(), true))->toBeGreaterThan(14);

    $engine->fireTimer($timerRow);

    $instance->refresh();
    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($timerRow->fresh()->status)->toBe(WorkflowTimerStatus::Fired);
});

test('a waiting subworkflow resumes the parent once the child instance completes', function () {
    $child = wfWorkflowWithVersion(['name' => 'Sotto-processo']);
    $childVersion = $child->currentVersion;
    $childStart = WorkflowNode::factory()->for($childVersion)->start()->create();
    $childEnd = WorkflowNode::factory()->for($childVersion)->end()->create();
    wfConnect($childStart, $childEnd);

    $parent = wfWorkflowWithVersion(['name' => 'Processo principale']);
    $parentVersion = $parent->currentVersion;
    $parentStart = WorkflowNode::factory()->for($parentVersion)->start()->create();
    $sub = WorkflowNode::factory()->for($parentVersion)->create([
        'type' => WorkflowNodeType::Subworkflow,
        'config' => ['workflow_id' => $child->id, 'wait_for_completion' => true],
    ]);
    $parentEnd = WorkflowNode::factory()->for($parentVersion)->end()->create();

    wfConnect($parentStart, $sub);
    wfConnect($sub, $parentEnd);

    $instance = app(WorkflowEngine::class)->start($parent);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed);

    $childInstance = WorkflowInstance::where('workflow_id', $child->id)->first();
    expect($childInstance->status)->toBe(WorkflowInstanceStatus::Completed);
});

test('a start_condition blocks the instance from being created at all', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create([
        'config' => ['trigger_type' => 'manual', 'start_condition' => ['>' => [['var' => 'importo'], 1000]]],
    ]);
    WorkflowNode::factory()->for($version)->end()->create();
    wfConnect($start, $version->nodes()->where('type', WorkflowNodeType::End->value)->first());

    $blocked = app(WorkflowEngine::class)->start($workflow, ['importo' => 10]);
    expect($blocked)->toBeNull();

    $allowed = app(WorkflowEngine::class)->start($workflow, ['importo' => 5000]);
    expect($allowed)->not->toBeNull()
        ->and($allowed->status)->toBe(WorkflowInstanceStatus::Completed);
});

test('starting a workflow with no published version throws', function () {
    $workflow = Workflow::factory()->create();

    app(WorkflowEngine::class)->start($workflow);
})->throws(RuntimeException::class, 'non ha ancora una versione pubblicata');
