<?php

use App\Enums\WorkflowInstanceStatus;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.workflows.index'))->assertRedirect(route('login'));
});

test('admin can view the workflows index and create page', function () {
    $admin = adminUser();
    $this->actingAs($admin)->get(route('admin.workflows.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.workflows.create'))->assertOk();
});

test('admin can create a workflow and is redirected straight to the builder', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.workflows.store'), [
        'name' => 'Approvazione spese',
        'description' => 'Flusso di test',
        'is_active' => '1',
    ]);

    $workflow = Workflow::firstWhere('name', 'Approvazione spese');

    expect($workflow)->not->toBeNull();
    $response->assertRedirect(route('admin.workflows.builder.edit', $workflow));
});

test('the edit route redirects to the builder', function () {
    $admin = adminUser();
    $workflow = Workflow::factory()->create();

    $this->actingAs($admin)->get(route('admin.workflows.edit', $workflow))
        ->assertRedirect(route('admin.workflows.builder.edit', $workflow));
});

test('admin can delete a workflow with no instances in flight', function () {
    $admin = adminUser();
    $workflow = Workflow::factory()->create();

    $this->actingAs($admin)->delete(route('admin.workflows.destroy', $workflow))
        ->assertRedirect(route('admin.workflows.index'));

    expect(Workflow::find($workflow->id))->toBeNull();
});

test('admin cannot delete a workflow with a running instance', function () {
    $admin = adminUser();
    $workflow = wfWorkflowWithVersion();
    WorkflowInstance::factory()->for($workflow)->for($workflow->currentVersion)->create(['status' => WorkflowInstanceStatus::Running]);

    $this->actingAs($admin)->delete(route('admin.workflows.destroy', $workflow))
        ->assertRedirect(route('admin.workflows.index'))
        ->assertSessionHas('error');

    expect(Workflow::find($workflow->id))->not->toBeNull();
});

test('admin can manually run a workflow from its detail page', function () {
    $admin = adminUser();
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $end = WorkflowNode::factory()->for($version)->end()->create();
    WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => $start->id, 'target_node_id' => $end->id]);

    $this->actingAs($admin)->post(route('admin.workflows.run', $workflow))
        ->assertRedirect(route('admin.workflows.show', $workflow));

    expect(WorkflowInstance::where('workflow_id', $workflow->id)->where('status', WorkflowInstanceStatus::Completed->value)->exists())->toBeTrue();
});

test('running a workflow with no published version shows an error instead of crashing', function () {
    $admin = adminUser();
    $workflow = Workflow::factory()->create();

    $this->actingAs($admin)->post(route('admin.workflows.run', $workflow))
        ->assertRedirect(route('admin.workflows.show', $workflow))
        ->assertSessionHas('error');
});

test('export produces a re-importable JSON graph', function () {
    $admin = adminUser();
    $workflow = wfWorkflowWithVersion();
    $version = $workflow->currentVersion;
    $start = WorkflowNode::factory()->for($version)->start()->create();
    $end = WorkflowNode::factory()->for($version)->end()->create();
    WorkflowEdge::create(['workflow_version_id' => $version->id, 'source_node_id' => $start->id, 'target_node_id' => $end->id]);

    $response = $this->actingAs($admin)->get(route('admin.workflows.export', $workflow));
    $response->assertOk();

    $tmp = tempnam(sys_get_temp_dir(), 'wf').'.json';
    file_put_contents($tmp, $response->getContent());

    $this->actingAs($admin)->post(route('admin.workflows.import'), [
        'file' => new UploadedFile($tmp, 'workflow.json', 'application/json', null, true),
    ])->assertRedirect();

    expect(Workflow::where('name', $workflow->name)->count())->toBe(2);
});
