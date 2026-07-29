<?php

use App\Enums\WorkflowActionPhase;
use App\Enums\WorkflowActionType;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowNodeExecutionStatus;
use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowUserTaskStatus;
use App\Models\WorkflowNode;
use App\Models\WorkflowNodeExecution;
use App\Services\Workflows\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// wfConnect() is defined once in WorkflowEngineTest.php (same directory,
// loaded together as part of the suite) — top-level test-file functions
// are truly global in PHP, so it's reused here rather than redeclared.

test('a linear workflow logs one completed, closed execution per node visited', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ServiceTask]);
    $end = WorkflowNode::factory()->for($version)->end()->create();

    wfConnect($start, $task);
    wfConnect($task, $end);

    $instance = app(WorkflowEngine::class)->start($workflow);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed);

    $executions = WorkflowNodeExecution::where('workflow_instance_id', $instance->id)->get();
    expect($executions)->toHaveCount(3)
        ->and($executions->pluck('workflow_node_id')->sort()->values()->all())
        ->toBe([$start->id, $task->id, $end->id])
        ->and($executions->every(fn ($e) => $e->status === WorkflowNodeExecutionStatus::Completed))->toBeTrue()
        ->and($executions->every(fn ($e) => $e->exited_at !== null))->toBeTrue()
        ->and($executions->every(fn ($e) => $e->iteration === 1))->toBeTrue();
});

test('a node revisited by a cycle logs one row per iteration, each snapshotting the variables at that moment', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $loopTask = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ServiceTask, 'name' => 'Incrementa']);
    $gate = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ExclusiveGateway]);
    $end = WorkflowNode::factory()->for($version)->end()->create();

    wfConnect($start, $loopTask);
    wfConnect($loopTask, $gate);
    wfConnect($gate, $loopTask, ['<' => [['var' => 'counter'], 3]], 0);
    wfConnect($gate, $end, ['>=' => [['var' => 'counter'], 3]], 1);

    $loopTask->actions()->create([
        'workflow_version_id' => $version->id,
        'phase' => WorkflowActionPhase::After,
        'sequence' => 0,
        'type' => WorkflowActionType::SetVariable,
        'config' => ['variable' => 'counter', 'expression' => 'counter + 1'],
    ]);

    $instance = app(WorkflowEngine::class)->start($workflow, ['counter' => 0]);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->getVariable('counter'))->toBe(3);

    $loopExecutions = WorkflowNodeExecution::where('workflow_instance_id', $instance->id)
        ->where('workflow_node_id', $loopTask->id)
        ->orderBy('iteration')
        ->get();

    expect($loopExecutions)->toHaveCount(3)
        ->and($loopExecutions->pluck('iteration')->all())->toBe([1, 2, 3])
        ->and($loopExecutions->pluck('variables_snapshot.counter')->all())->toBe([0, 1, 2])
        ->and($loopExecutions->every(fn ($e) => $e->status === WorkflowNodeExecutionStatus::Completed))->toBeTrue();

    $gateExecutions = WorkflowNodeExecution::where('workflow_instance_id', $instance->id)->where('workflow_node_id', $gate->id)->get();
    expect($gateExecutions)->toHaveCount(3);
});

test('a pending user task leaves its node execution open and waiting', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::UserTask]);
    $end = WorkflowNode::factory()->for($version)->end()->create();

    wfConnect($start, $task);
    wfConnect($task, $end);

    $instance = app(WorkflowEngine::class)->start($workflow);
    $userTask = $instance->userTasks()->first();
    expect($userTask->status)->toBe(WorkflowUserTaskStatus::Pending);

    $execution = WorkflowNodeExecution::where('workflow_instance_id', $instance->id)->where('workflow_node_id', $task->id)->sole();
    expect($execution->status)->toBe(WorkflowNodeExecutionStatus::Waiting)
        ->and($execution->exited_at)->toBeNull();

    app(WorkflowEngine::class)->completeUserTask($userTask, [], null);

    expect($execution->fresh()->status)->toBe(WorkflowNodeExecutionStatus::Completed);
});

test('a failed instance marks the node it failed on as failed, not left waiting', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $gate = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ExclusiveGateway]);
    $end = WorkflowNode::factory()->for($version)->end()->create();

    wfConnect($start, $gate);
    wfConnect($gate, $end, ['>' => [['var' => 'importo'], 100]]);

    $instance = app(WorkflowEngine::class)->start($workflow, ['importo' => 10]);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Failed);

    $execution = WorkflowNodeExecution::where('workflow_instance_id', $instance->id)->where('workflow_node_id', $gate->id)->sole();
    expect($execution->status)->toBe(WorkflowNodeExecutionStatus::Failed)
        ->and($execution->exited_at)->not->toBeNull();
});
