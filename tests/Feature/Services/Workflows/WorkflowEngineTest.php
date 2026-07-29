<?php

use App\Enums\EntityFieldType;
use App\Enums\WorkflowActionPhase;
use App\Enums\WorkflowActionType;
use App\Enums\WorkflowActivityExecutionStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowTimerStatus;
use App\Enums\WorkflowTokenStatus;
use App\Enums\WorkflowUserTaskStatus;
use App\Jobs\Workflows\ExecuteServiceTaskJob;
use App\Models\Entity;
use App\Models\EntityCard;
use App\Models\EntityRecord;
use App\Models\EntityTab;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivityExecution;
use App\Models\WorkflowEdge;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNode;
use App\Services\EntityInstaller;
use App\Services\Workflows\TaskExecutors\SyncTaskExecutor;
use App\Services\Workflows\WorkflowEngine;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

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

test('a clear_variable action resets a previously set variable to null', function () {
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
        'type' => WorkflowActionType::ClearVariable,
        'config' => ['variable' => 'saluto'],
    ]);

    $instance = app(WorkflowEngine::class)->start($workflow, ['saluto' => 'ciao mondo']);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->getVariable('saluto'))->toBeNull();
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

test('an exclusive gate routes on a "changed_to" condition against the entity that started the instance', function () {
    $entity = Entity::create(['name' => 'Ordine Gate', 'slug' => 'ordine-gate-'.uniqid(), 'table_name' => 'entity_ordine_gate_'.uniqid()]);
    $tab = $entity->tabs()->create(['name' => 'Generale', 'position' => 0]);
    $card = $tab->cards()->create(['name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Stato', 'column_name' => 'stato', 'type' => EntityFieldType::String, 'position' => 0]);
    app(EntityInstaller::class)->install($entity);
    $record = EntityRecord::forEntity($entity)->create(['stato' => 'chiuso', 'user_id' => User::factory()->create()->id]);

    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $gate = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ExclusiveGateway]);
    $endChanged = WorkflowNode::factory()->for($version)->end()->create(['name' => 'Appena chiuso']);
    $endOther = WorkflowNode::factory()->for($version)->end()->create(['name' => 'Altro']);

    wfConnect($start, $gate);
    wfConnect($gate, $endChanged, ['changed_to' => [['var' => 'entity.stato'], 'chiuso']], 0);
    wfConnect($gate, $endOther, null, 1);

    $instance = app(WorkflowEngine::class)->start(
        $workflow,
        ['__entity_previous' => ['stato' => 'aperto']],
        $record,
        entitySlug: $entity->slug,
    );

    expect($instance->tokens()->first()->workflow_node_id)->toBe($endChanged->id);
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

test('a user task with assignment_mode user assigns the fixed user directly', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $assignee = User::factory()->create();
    $task = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::UserTask,
        'config' => ['assignment_mode' => 'user', 'assigned_user_id' => $assignee->id],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    wfConnect($start, $task);
    wfConnect($task, $end);

    $instance = app(WorkflowEngine::class)->start($workflow);

    $userTask = $instance->userTasks()->first();
    expect($userTask->assigned_user_id)->toBe($assignee->id)
        ->and($userTask->assigned_role_id)->toBeNull();
});

test('a user task with assignment_mode role keeps the existing role-based behaviour', function () {
    $role = Role::create(['name' => 'Revisore', 'slug' => 'revisore-'.uniqid()]);
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::UserTask,
        'config' => ['assignment_mode' => 'role', 'assigned_role_id' => $role->id],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    wfConnect($start, $task);
    wfConnect($task, $end);

    $instance = app(WorkflowEngine::class)->start($workflow);

    $userTask = $instance->userTasks()->first();
    expect($userTask->assigned_role_id)->toBe($role->id)
        ->and($userTask->assigned_user_id)->toBeNull();
});

test('a user task without assignment_mode in config falls back to role for backward compatibility', function () {
    $role = Role::create(['name' => 'Revisore legacy', 'slug' => 'revisore-legacy-'.uniqid()]);
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::UserTask,
        'config' => ['assigned_role_id' => $role->id],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    wfConnect($start, $task);
    wfConnect($task, $end);

    $instance = app(WorkflowEngine::class)->start($workflow);

    $userTask = $instance->userTasks()->first();
    expect($userTask->assigned_role_id)->toBe($role->id)
        ->and($userTask->assigned_user_id)->toBeNull();
});

test('a user task with assignment_mode expression resolves the assignee from the triggering entity', function () {
    $entity = Entity::create(['name' => 'Pratica WF', 'slug' => 'pratica-wf-'.uniqid(), 'table_name' => 'entity_pratica_wf_'.uniqid()]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Titolo', 'column_name' => 'titolo', 'type' => EntityFieldType::String, 'position' => 0]);
    app(EntityInstaller::class)->install($entity);

    $responsabile = User::factory()->create();
    $record = EntityRecord::forEntity($entity)->create(['titolo' => 'Pratica 1', 'user_id' => $responsabile->id]);

    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create([
        'config' => ['trigger_type' => 'entity_created', 'entity_slug' => $entity->slug],
    ]);
    $task = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::UserTask,
        'config' => ['assignment_mode' => 'expression', 'assignee_expression' => 'entity.user_id'],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    wfConnect($start, $task);
    wfConnect($task, $end);

    $instance = app(WorkflowEngine::class)->start($workflow, [], $record, null, $entity->slug);

    $userTask = $instance->userTasks()->first();
    expect($userTask->assigned_user_id)->toBe($responsabile->id)
        ->and($userTask->assigned_role_id)->toBeNull();
});

test('a user task with assignment_mode expression resolving to a nonexistent user leaves it unassigned', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::UserTask,
        'config' => ['assignment_mode' => 'expression', 'assignee_expression' => '999999'],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    wfConnect($start, $task);
    wfConnect($task, $end);

    $instance = app(WorkflowEngine::class)->start($workflow);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Running);

    $userTask = $instance->userTasks()->first();
    expect($userTask->assigned_user_id)->toBeNull()
        ->and($userTask->assigned_role_id)->toBeNull();
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

test('a service task defaults to running synchronously in-process', function () {
    Queue::fake();

    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create(['type' => WorkflowNodeType::ServiceTask]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    wfConnect($start, $task);
    wfConnect($task, $end);

    $instance = app(WorkflowEngine::class)->start($workflow);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed);
    Queue::assertNothingPushed();
});

test('a service task with execution_mode async parks its token and dispatches a queued job', function () {
    Queue::fake();

    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::ServiceTask,
        'config' => ['execution_mode' => 'async'],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    wfConnect($start, $task);
    wfConnect($task, $end);

    $instance = app(WorkflowEngine::class)->start($workflow);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Running)
        ->and($instance->tokens()->first()->status)->toBe(WorkflowTokenStatus::WaitingActivity);

    Queue::assertPushed(ExecuteServiceTaskJob::class, fn (ExecuteServiceTaskJob $job) => $job->node->is($task) && $job->instance->is($instance));
});

test('running the queued job for a service task applies its actions and resumes the instance', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::ServiceTask,
        'config' => ['execution_mode' => 'async'],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    wfConnect($start, $task);
    wfConnect($task, $end);

    $task->actions()->create([
        'workflow_version_id' => $version->id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::SetVariable,
        'config' => ['variable' => 'saluto', 'expression' => "'ciao ' ~ 'mondo'"],
    ]);

    $instance = app(WorkflowEngine::class)->start($workflow);
    $token = $instance->tokens()->first();

    $job = new ExecuteServiceTaskJob($task, $instance, $token);
    $job->handle(app(SyncTaskExecutor::class), app(WorkflowEngine::class));

    $instance->refresh();
    expect($instance->status)->toBe(WorkflowInstanceStatus::Completed)
        ->and($instance->getVariable('saluto'))->toBe('ciao mondo')
        ->and($token->fresh()->status)->toBe(WorkflowTokenStatus::Completed)
        ->and(WorkflowActivityExecution::where('workflow_token_id', $token->id)->first()->status)->toBe(WorkflowActivityExecutionStatus::Completed);
});

test('a redelivered job for an already-completed service task activity is a no-op', function () {
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $task = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::ServiceTask,
        'config' => ['execution_mode' => 'async'],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    wfConnect($start, $task);
    wfConnect($task, $end);

    $version->variables()->create(['name' => 'contatore', 'type' => 'integer', 'default_value' => 0]);
    $task->actions()->create([
        'workflow_version_id' => $version->id,
        'phase' => WorkflowActionPhase::After,
        'type' => WorkflowActionType::SetVariable,
        'config' => ['variable' => 'contatore', 'expression' => 'contatore + 1'],
    ]);

    $instance = app(WorkflowEngine::class)->start($workflow);
    $token = $instance->tokens()->first();

    $job = new ExecuteServiceTaskJob($task, $instance, $token);
    $job->handle(app(SyncTaskExecutor::class), app(WorkflowEngine::class));
    $job->handle(app(SyncTaskExecutor::class), app(WorkflowEngine::class));

    $instance->refresh();
    expect($instance->getVariable('contatore'))->toBe(1)
        ->and(WorkflowActivityExecution::where('workflow_token_id', $token->id)->count())->toBe(1);
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
