<?php

use Fazzinipierluigi\CrmCore\Enums\WorkflowInstanceStatus;
use Fazzinipierluigi\CrmCore\Enums\WorkflowNodeType;
use Fazzinipierluigi\CrmCore\Models\Workflow;
use Fazzinipierluigi\CrmCore\Models\WorkflowEdge;
use Fazzinipierluigi\CrmCore\Models\WorkflowNode;
use Fazzinipierluigi\CrmCore\Services\Workflows\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function timerWorkflow(): Workflow
{
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $timer = WorkflowNode::factory()->for($version)->create([
        'type' => WorkflowNodeType::Timer,
        'config' => ['reference' => 'fixed', 'date' => now()->subMinute()->toDateTimeString(), 'direction' => 'after', 'amount' => 0, 'unit' => 'minutes'],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => $start->id, 'target_node_id' => $timer->id]);
    WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => $timer->id, 'target_node_id' => $end->id]);

    return $workflow;
}

test('fires a due timer and resumes the instance', function () {
    $workflow = timerWorkflow();
    $instance = app(WorkflowEngine::class)->start($workflow);

    expect($instance->status)->toBe(WorkflowInstanceStatus::Running);

    $this->artisan('workflows:fire-due-timers')->assertSuccessful();

    expect($instance->fresh()->status)->toBe(WorkflowInstanceStatus::Completed);
});
