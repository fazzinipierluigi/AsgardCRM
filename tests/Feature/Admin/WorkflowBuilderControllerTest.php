<?php

use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function wfBuilderGraphPayload(): array
{
    return [
        'name' => 'Onboarding cliente',
        'description' => null,
        'is_active' => true,
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

test('admin can save a whole graph via the builder update endpoint', function () {
    $admin = adminUser();
    $workflow = Workflow::factory()->create();

    $response = $this->actingAs($admin)->putJson(route('admin.workflows.builder.update', $workflow), wfBuilderGraphPayload());

    $response->assertOk()->assertJson(['status' => 'ok']);

    $workflow->refresh();
    $version = $workflow->currentVersion;
    expect($version->version)->toBe(1)
        ->and($version->nodes)->toHaveCount(2)
        ->and($version->edges)->toHaveCount(1)
        ->and($version->variables)->toHaveCount(1);
});

test('saving a second time publishes version 2 without touching version 1', function () {
    $admin = adminUser();
    $workflow = Workflow::factory()->create();

    $this->actingAs($admin)->putJson(route('admin.workflows.builder.update', $workflow), wfBuilderGraphPayload())->assertOk();
    $v1 = $workflow->fresh()->currentVersion;

    $this->actingAs($admin)->putJson(route('admin.workflows.builder.update', $workflow), wfBuilderGraphPayload())->assertOk();
    $v2 = $workflow->fresh()->currentVersion;

    expect($v1->id)->not->toBe($v2->id)
        ->and($v2->version)->toBe(2)
        ->and($workflow->fresh()->current_version_id)->toBe($v2->id);
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
