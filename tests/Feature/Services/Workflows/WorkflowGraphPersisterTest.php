<?php

use App\Enums\WorkflowInstanceStatus;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Services\Workflows\WorkflowGraphPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function wfSampleGraph(): array
{
    return [
        'name' => 'Approvazione spese',
        'description' => 'Approva una spesa oltre soglia',
        'is_active' => true,
        'variables' => [
            ['name' => 'importo', 'type' => 'float', 'default_value' => '0'],
        ],
        'nodes' => [
            ['key' => 'n1', 'type' => 'start', 'name' => 'Avvio', 'pos_x' => 0, 'pos_y' => 0, 'config' => ['trigger_type' => 'manual'], 'actions' => []],
            ['key' => 'n2', 'type' => 'exclusive_gateway', 'name' => 'Sopra soglia?', 'pos_x' => 200, 'pos_y' => 0, 'config' => [], 'actions' => []],
            ['key' => 'n3', 'type' => 'end', 'name' => 'Fine', 'pos_x' => 400, 'pos_y' => 0, 'config' => [], 'actions' => [
                'after' => [['type' => 'set_variable', 'config' => ['variable' => 'importo', 'expression' => '1']]],
            ]],
        ],
        'edges' => [
            ['source_key' => 'n1', 'target_key' => 'n2', 'sequence' => 0],
            ['source_key' => 'n2', 'target_key' => 'n3', 'sequence' => 0, 'condition_logic' => ['>' => [['var' => 'importo'], 100]]],
        ],
    ];
}

test('replace publishes a version 1 and toArray hydrates it back with real keys', function () {
    $workflow = Workflow::factory()->create();

    $version = app(WorkflowGraphPersister::class)->replace($workflow, wfSampleGraph());

    $workflow->refresh();

    expect($workflow->name)->toBe('Approvazione spese')
        ->and($version->version)->toBe(1)
        ->and($workflow->current_version_id)->toBe($version->id)
        ->and($version->nodes)->toHaveCount(3)
        ->and($version->edges)->toHaveCount(2)
        ->and($version->variables)->toHaveCount(1);

    $array = app(WorkflowGraphPersister::class)->toArray($workflow);

    expect($array['nodes'])->toHaveCount(3)
        ->and($array['edges'])->toHaveCount(2)
        ->and(collect($array['nodes'])->firstWhere('type', 'end')['actions']['after'][0]['type'])->toBe('set_variable');
});

test('replace round-trips manually-dragged edge waypoints instead of losing them', function () {
    $workflow = Workflow::factory()->create();
    $graph = wfSampleGraph();
    $graph['edges'][1]['waypoints'] = [['x' => 250, 'y' => 80], ['x' => 350, 'y' => 80]];

    app(WorkflowGraphPersister::class)->replace($workflow, $graph);

    $array = app(WorkflowGraphPersister::class)->toArray($workflow);

    expect(collect($array['edges'])->firstWhere('condition_logic'))
        ->waypoints->toBe([['x' => 250, 'y' => 80], ['x' => 350, 'y' => 80]]);
});

test('replace rejects a graph without exactly one start node', function () {
    $workflow = Workflow::factory()->create();
    $graph = wfSampleGraph();
    $graph['nodes'][0]['type'] = 'service_task';

    app(WorkflowGraphPersister::class)->replace($workflow, $graph);
})->throws(RuntimeException::class, 'nodo di avvio');

test('editing a workflow with an instance in flight publishes a new version without disturbing it', function () {
    $persister = app(WorkflowGraphPersister::class);
    $workflow = Workflow::factory()->create();

    $v1 = $persister->replace($workflow, wfSampleGraph());

    $runningInstance = WorkflowInstance::factory()->for($workflow)->for($v1)->create(['status' => WorkflowInstanceStatus::Running]);

    $graph2 = wfSampleGraph();
    $graph2['nodes'][2]['name'] = 'Fine (modificata)';
    $v2 = $persister->replace($workflow, $graph2);

    expect($v2->version)->toBe(2)
        ->and($workflow->fresh()->current_version_id)->toBe($v2->id)
        ->and($runningInstance->fresh()->workflow_version_id)->toBe($v1->id)
        ->and($v1->fresh()->nodes()->where('type', 'end')->first()->name)->toBe('Fine');
});
