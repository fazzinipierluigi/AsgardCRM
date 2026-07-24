<?php

use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function wfBuilderGraphPayload(): array
{
    return [
        'variables' => [
            ['name' => 'esito', 'type' => 'string', 'default_value' => null],
        ],
        'nodes' => [
            ['key' => 'n1', 'type' => 'start', 'name' => 'Avvio', 'pos_x' => 0, 'pos_y' => 0, 'config' => ['trigger_type' => 'manual'], 'actions' => ['before' => [], 'after' => []]],
            ['key' => 'n2', 'type' => 'end', 'name' => 'Fine', 'pos_x' => 200, 'pos_y' => 0, 'config' => [], 'actions' => ['before' => [], 'after' => []]],
        ],
        'edges' => [
            ['source_key' => 'n1', 'target_key' => 'n2', 'label' => null, 'sequence' => 0, 'condition_logic' => null, 'actions' => ['before' => [], 'after' => []]],
        ],
    ];
}

test('admin can view the builder page for a fresh workflow', function () {
    $admin = adminUser();
    $workflow = Workflow::factory()->create();

    $this->actingAs($admin)->get(route('admin.workflows.builder.edit', $workflow))->assertOk();
});

test('admin can save a whole graph as a draft via the builder update endpoint', function () {
    $admin = adminUser();
    $workflow = Workflow::factory()->create();

    $response = $this->actingAs($admin)->putJson(route('admin.workflows.builder.update', $workflow), wfBuilderGraphPayload());

    $response->assertOk()->assertJson(['status' => 'ok']);

    $workflow->refresh();
    $draft = $workflow->versions()->where('status', WorkflowVersionStatus::Draft->value)->first();
    expect($draft->version)->toBe(1)
        ->and($draft->nodes)->toHaveCount(2)
        ->and($draft->edges)->toHaveCount(1)
        ->and($draft->variables)->toHaveCount(1)
        ->and($workflow->current_version_id)->toBeNull();
});

test('saving a second time reuses the same draft without touching a published version', function () {
    $admin = adminUser();
    $workflow = Workflow::factory()->create();

    $this->actingAs($admin)->putJson(route('admin.workflows.builder.update', $workflow), wfBuilderGraphPayload())->assertOk();
    $this->actingAs($admin)->postJson(route('admin.workflows.builder.publish', $workflow))->assertOk();
    $v1 = $workflow->fresh()->currentVersion;

    $this->actingAs($admin)->putJson(route('admin.workflows.builder.update', $workflow), wfBuilderGraphPayload())->assertOk();

    $workflow->refresh();
    $draft = $workflow->versions()->where('status', WorkflowVersionStatus::Draft->value)->first();

    expect($draft->version)->toBe(2)
        ->and($workflow->current_version_id)->toBe($v1->id)
        ->and($v1->fresh()->status)->toBe(WorkflowVersionStatus::Published);

    $this->actingAs($admin)->putJson(route('admin.workflows.builder.update', $workflow), wfBuilderGraphPayload())->assertOk();
    expect($workflow->versions()->count())->toBe(2);
});

test('publish makes the current draft live', function () {
    $admin = adminUser();
    $workflow = Workflow::factory()->create();

    $this->actingAs($admin)->putJson(route('admin.workflows.builder.update', $workflow), wfBuilderGraphPayload())->assertOk();

    $response = $this->actingAs($admin)->postJson(route('admin.workflows.builder.publish', $workflow));

    $response->assertOk()->assertJson(['status' => 'ok', 'version' => 1]);

    $workflow->refresh();
    expect($workflow->currentVersion->status)->toBe(WorkflowVersionStatus::Published);
});

test('publishing without a draft returns a 422', function () {
    $admin = adminUser();
    $workflow = Workflow::factory()->create();

    $this->actingAs($admin)->postJson(route('admin.workflows.builder.publish', $workflow))
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Non c\'è nessuna bozza da pubblicare.']);
});

test('saving a graph without a start node returns a 422 with a clear message', function () {
    $admin = adminUser();
    $workflow = Workflow::factory()->create();
    $payload = wfBuilderGraphPayload();
    $payload['nodes'][0]['type'] = 'service_task';

    $this->actingAs($admin)->putJson(route('admin.workflows.builder.update', $workflow), $payload)
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'Il workflow deve avere esattamente un nodo di avvio.']);
});
