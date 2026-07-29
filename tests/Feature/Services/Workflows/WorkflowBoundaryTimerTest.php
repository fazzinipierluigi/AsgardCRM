<?php

use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowTimerStatus;
use App\Enums\WorkflowTokenStatus;
use App\Enums\WorkflowUserTaskStatus;
use App\Jobs\Workflows\ExecuteServiceTaskJob;
use App\Models\WorkflowNode;
use App\Services\Workflows\TaskExecutors\SyncTaskExecutor;
use App\Services\Workflows\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function wfUserTaskWithBoundaryTimer(): array
{
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $host = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::UserTask, 'name' => 'Approvazione']);
    $endNormal = WorkflowNode::factory()->for($version)->end()->create(['name' => 'Approvato']);
    $endTimeout = WorkflowNode::factory()->for($version)->end()->create(['name' => 'Scaduto']);
    $boundary = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::BoundaryTimer,
        'name' => 'Timeout approvazione',
        'config' => ['attached_to_node_id' => $host->id, 'reference' => 'fixed', 'date' => now()->addDay()->toDateTimeString(), 'amount' => 0, 'unit' => 'minutes', 'direction' => 'after'],
    ]);

    wfConnect($start, $host);
    wfConnect($host, $endNormal);
    wfConnect($boundary, $endTimeout);

    return compact('workflow', 'host', 'endNormal', 'endTimeout', 'boundary');
}

test('completing the host user task before the boundary timer fires cancels it and follows the normal path', function () {
    ['workflow' => $workflow, 'endNormal' => $endNormal] = wfUserTaskWithBoundaryTimer();

    $instance = app(WorkflowEngine::class)->start($workflow);
    $task = $instance->userTasks()->first();
    $timer = $instance->timers()->first();

    expect($timer->status)->toBe(WorkflowTimerStatus::Pending);

    app(WorkflowEngine::class)->completeUserTask($task, [], null);

    expect($instance->fresh()->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->fresh()->tokens()->first()->workflow_node_id)->toBe($endNormal->id)
        ->and($timer->fresh()->status)->toBe(WorkflowTimerStatus::Cancelled);
});

test('the boundary timer firing before completion expires the host task and follows the timeout path', function () {
    ['workflow' => $workflow, 'endTimeout' => $endTimeout] = wfUserTaskWithBoundaryTimer();

    $instance = app(WorkflowEngine::class)->start($workflow);
    $task = $instance->userTasks()->first();
    $timer = $instance->timers()->first();

    app(WorkflowEngine::class)->fireTimer($timer);

    expect($timer->fresh()->status)->toBe(WorkflowTimerStatus::Fired)
        ->and($task->fresh()->status)->toBe(WorkflowUserTaskStatus::Expired)
        ->and($instance->fresh()->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->fresh()->tokens()->first()->workflow_node_id)->toBe($endTimeout->id);
});

test('firing an already-cancelled boundary timer is a no-op', function () {
    ['workflow' => $workflow] = wfUserTaskWithBoundaryTimer();

    $instance = app(WorkflowEngine::class)->start($workflow);
    $task = $instance->userTasks()->first();
    $timer = $instance->timers()->first();

    app(WorkflowEngine::class)->completeUserTask($task, [], null);
    expect($timer->fresh()->status)->toBe(WorkflowTimerStatus::Cancelled);

    app(WorkflowEngine::class)->fireTimer($timer->fresh());

    expect($timer->fresh()->status)->toBe(WorkflowTimerStatus::Cancelled)
        ->and($instance->fresh()->status)->toBe(WorkflowInstanceStatus::Completed);
});

function wfAsyncServiceTaskWithBoundaryTimer(): array
{
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $host = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::ServiceTask,
        'name' => 'Chiamata esterna',
        'config' => ['execution_mode' => 'async'],
    ]);
    $endNormal = WorkflowNode::factory()->for($version)->end()->create(['name' => 'Riuscita']);
    $endTimeout = WorkflowNode::factory()->for($version)->end()->create(['name' => 'Timeout']);
    $boundary = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::BoundaryTimer,
        'name' => 'Timeout chiamata',
        'config' => ['attached_to_node_id' => $host->id, 'reference' => 'fixed', 'date' => now()->addDay()->toDateTimeString(), 'amount' => 0, 'unit' => 'minutes', 'direction' => 'after'],
    ]);

    wfConnect($start, $host);
    wfConnect($host, $endNormal);
    wfConnect($boundary, $endTimeout);

    return compact('workflow', 'host', 'endNormal', 'endTimeout', 'boundary');
}

test('the queued job completing an async service task before the boundary fires cancels the timer', function () {
    Queue::fake();
    ['workflow' => $workflow, 'host' => $host, 'endNormal' => $endNormal] = wfAsyncServiceTaskWithBoundaryTimer();

    $instance = app(WorkflowEngine::class)->start($workflow);
    $token = $instance->tokens()->first();
    $timer = $instance->timers()->first();

    expect($token->status)->toBe(WorkflowTokenStatus::WaitingActivity)
        ->and($timer->status)->toBe(WorkflowTimerStatus::Pending);

    $job = new ExecuteServiceTaskJob($host, $instance, $token);
    $job->handle(app(SyncTaskExecutor::class), app(WorkflowEngine::class));

    expect($instance->fresh()->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->fresh()->tokens()->first()->workflow_node_id)->toBe($endNormal->id)
        ->and($timer->fresh()->status)->toBe(WorkflowTimerStatus::Cancelled);
});

test('a late-arriving job for an async service task whose boundary already fired is a no-op', function () {
    Queue::fake();
    ['workflow' => $workflow, 'host' => $host, 'endTimeout' => $endTimeout] = wfAsyncServiceTaskWithBoundaryTimer();

    $instance = app(WorkflowEngine::class)->start($workflow);
    $token = $instance->tokens()->first();
    $timer = $instance->timers()->first();

    app(WorkflowEngine::class)->fireTimer($timer);

    expect($instance->fresh()->tokens()->first()->workflow_node_id)->toBe($endTimeout->id)
        ->and($instance->fresh()->tokens()->first()->status)->toBe(WorkflowTokenStatus::Completed);

    $job = new ExecuteServiceTaskJob($host, $instance, $token);
    $job->handle(app(SyncTaskExecutor::class), app(WorkflowEngine::class));

    expect($instance->fresh()->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->fresh()->tokens()->first()->workflow_node_id)->toBe($endTimeout->id);
});
