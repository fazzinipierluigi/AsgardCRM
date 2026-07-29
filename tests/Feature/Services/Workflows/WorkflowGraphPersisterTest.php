<?php

use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowVersionStatus;
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

test('replace writes a draft version 1 that is not yet live, and toArray hydrates it back with real keys', function () {
    $workflow = Workflow::factory()->create();

    $version = app(WorkflowGraphPersister::class)->replace($workflow, wfSampleGraph());

    $workflow->refresh();

    expect($version->version)->toBe(1)
        ->and($version->status)->toBe(WorkflowVersionStatus::Draft)
        ->and($workflow->current_version_id)->toBeNull()
        ->and($version->nodes)->toHaveCount(3)
        ->and($version->edges)->toHaveCount(2)
        ->and($version->variables)->toHaveCount(1);

    $array = app(WorkflowGraphPersister::class)->toArray($workflow);

    expect($array['version'])->toBe(1)
        ->and($array['version_status'])->toBe('draft')
        ->and($array['published_version'])->toBeNull()
        ->and($array['nodes'])->toHaveCount(3)
        ->and($array['edges'])->toHaveCount(2)
        ->and(collect($array['nodes'])->firstWhere('type', 'end')['actions']['after'][0]['type'])->toBe('set_variable');
});

test('saving the draft again reuses the same version instead of minting a new one', function () {
    $workflow = Workflow::factory()->create();
    $persister = app(WorkflowGraphPersister::class);

    $v1 = $persister->replace($workflow, wfSampleGraph());

    $graph2 = wfSampleGraph();
    $graph2['nodes'][2]['name'] = 'Fine (modificata)';
    $v1SavedAgain = $persister->replace($workflow, $graph2);

    expect($v1SavedAgain->id)->not->toBe($v1->id)
        ->and($v1SavedAgain->version)->toBe(1)
        ->and($workflow->versions()->count())->toBe(1)
        ->and($v1SavedAgain->nodes()->where('type', 'end')->first()->name)->toBe('Fine (modificata)');
});

test('publish promotes the current draft to live and stops it from being overwritten by the next save', function () {
    $workflow = Workflow::factory()->create();
    $persister = app(WorkflowGraphPersister::class);

    $draft = $persister->replace($workflow, wfSampleGraph());
    $published = $persister->publish($workflow);

    expect($published->id)->toBe($draft->id)
        ->and($published->status)->toBe(WorkflowVersionStatus::Published)
        ->and($published->published_at)->not->toBeNull()
        ->and($workflow->fresh()->current_version_id)->toBe($published->id);

    $graph2 = wfSampleGraph();
    $graph2['nodes'][2]['name'] = 'Fine v2';
    $v2 = $persister->replace($workflow, $graph2);

    expect($v2->version)->toBe(2)
        ->and($v2->status)->toBe(WorkflowVersionStatus::Draft)
        ->and($workflow->fresh()->current_version_id)->toBe($published->id)
        ->and($published->fresh()->nodes()->where('type', 'end')->first()->name)->toBe('Fine');
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

test('publishing with no draft throws', function () {
    $workflow = Workflow::factory()->create();

    app(WorkflowGraphPersister::class)->publish($workflow);
})->throws(RuntimeException::class, 'nessuna bozza');

test('replace rejects a graph without exactly one start node', function () {
    $workflow = Workflow::factory()->create();
    $graph = wfSampleGraph();
    $graph['nodes'][0]['type'] = 'service_task';

    app(WorkflowGraphPersister::class)->replace($workflow, $graph);
})->throws(RuntimeException::class, 'nodo di avvio');

test('replace resolves a Boundary Timer\'s host key to a real node id', function () {
    $workflow = Workflow::factory()->create();
    $graph = wfSampleGraph();
    $graph['nodes'][] = ['key' => 'n4', 'type' => 'boundary_timer', 'name' => 'Timeout', 'pos_x' => 0, 'pos_y' => 0, 'config' => ['attached_to_node_key' => 'n2'], 'actions' => []];
    // n2 is the exclusive_gateway in wfSampleGraph() — swap it to a
    // user_task, an allowed Boundary Timer host.
    $graph['nodes'][1]['type'] = 'user_task';

    $version = app(WorkflowGraphPersister::class)->replace($workflow, $graph);

    $host = $version->nodes()->where('key', '!=', null)->where('name', $graph['nodes'][1]['name'])->firstOrFail();
    $boundary = $version->nodes()->where('type', 'boundary_timer')->firstOrFail();

    expect($boundary->config)->not->toHaveKey('attached_to_node_key')
        ->and($boundary->config['attached_to_node_id'])->toBe($host->id);
});

test('replace rejects a Boundary Timer attached to a node that isn\'t a User Task or an async Task processo/script', function () {
    $workflow = Workflow::factory()->create();
    $graph = wfSampleGraph();
    $graph['nodes'][] = ['key' => 'n4', 'type' => 'boundary_timer', 'name' => 'Timeout', 'pos_x' => 0, 'pos_y' => 0, 'config' => ['attached_to_node_key' => 'n2'], 'actions' => []];

    app(WorkflowGraphPersister::class)->replace($workflow, $graph);
})->throws(RuntimeException::class, 'Task utente');

test('replace rejects a Boundary Timer with no host key at all', function () {
    $workflow = Workflow::factory()->create();
    $graph = wfSampleGraph();
    $graph['nodes'][] = ['key' => 'n4', 'type' => 'boundary_timer', 'name' => 'Timeout', 'pos_x' => 0, 'pos_y' => 0, 'config' => [], 'actions' => []];

    app(WorkflowGraphPersister::class)->replace($workflow, $graph);
})->throws(RuntimeException::class, 'nodo esistente');

test('publishing a new draft while an instance is running keeps that instance pinned to its version', function () {
    $persister = app(WorkflowGraphPersister::class);
    $workflow = Workflow::factory()->create();

    $v1 = $persister->replace($workflow, wfSampleGraph());
    $persister->publish($workflow);

    $runningInstance = WorkflowInstance::factory()->for($workflow)->for($v1)->create(['status' => WorkflowInstanceStatus::Running]);

    $graph2 = wfSampleGraph();
    $graph2['nodes'][2]['name'] = 'Fine (modificata)';
    $draft2 = $persister->replace($workflow, $graph2);
    $v2 = $persister->publish($workflow);

    expect($v2->id)->toBe($draft2->id)
        ->and($v2->version)->toBe(2)
        ->and($workflow->fresh()->current_version_id)->toBe($v2->id)
        ->and($runningInstance->fresh()->workflow_version_id)->toBe($v1->id)
        ->and($v1->fresh()->nodes()->where('type', 'end')->first()->name)->toBe('Fine');
});
