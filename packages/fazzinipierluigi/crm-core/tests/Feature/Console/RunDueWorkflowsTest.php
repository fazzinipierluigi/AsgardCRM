<?php

use Fazzinipierluigi\CrmCore\Enums\WorkflowInstanceStatus;
use Fazzinipierluigi\CrmCore\Models\Workflow;
use Fazzinipierluigi\CrmCore\Models\WorkflowEdge;
use Fazzinipierluigi\CrmCore\Models\WorkflowInstance;
use Fazzinipierluigi\CrmCore\Models\WorkflowNode;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cronWorkflow(string $cronExpression = '* * * * *'): Workflow
{
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create([
        'config' => ['trigger_type' => 'cron', 'cron_expression' => $cronExpression],
    ]);
    $end = WorkflowNode::factory()->for($version)->end()->create();
    WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => $start->id, 'target_node_id' => $end->id]);

    return $workflow;
}

test('starts an instance for a workflow whose cron trigger is due', function () {
    $workflow = cronWorkflow();

    $this->artisan('workflows:run-due')->assertSuccessful();

    expect(WorkflowInstance::where('workflow_id', $workflow->id)->where('status', WorkflowInstanceStatus::Completed->value)->exists())->toBeTrue()
        ->and($workflow->fresh()->last_cron_run_at)->not->toBeNull();
});

test('does not start a workflow whose cron trigger is not due', function () {
    $workflow = cronWorkflow('0 0 1 1 *');

    $this->artisan('workflows:run-due')->assertSuccessful();

    expect(WorkflowInstance::where('workflow_id', $workflow->id)->exists())->toBeFalse();
});

test('does not double-start a workflow already run this minute', function () {
    $workflow = cronWorkflow();
    $workflow->update(['last_cron_run_at' => now()]);

    $this->artisan('workflows:run-due')->assertSuccessful();

    expect(WorkflowInstance::where('workflow_id', $workflow->id)->count())->toBe(0);
});

test('ignores a manual-trigger workflow', function () {
    $workflow = wfWorkflowWithVersion();
    WorkflowNode::factory()->for($workflow->currentVersion)->start()->create(['config' => ['trigger_type' => 'manual']]);

    $this->artisan('workflows:run-due')->assertSuccessful();

    expect(WorkflowInstance::where('workflow_id', $workflow->id)->exists())->toBeFalse();
});

test('ignores a workflow with no published version', function () {
    $workflow = Workflow::factory()->create();

    $this->artisan('workflows:run-due')->assertSuccessful();

    expect(WorkflowInstance::where('workflow_id', $workflow->id)->exists())->toBeFalse();
});
