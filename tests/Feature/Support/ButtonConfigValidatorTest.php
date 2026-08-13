<?php

use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Support\ButtonConfigValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a missing or invalid action is rejected', function () {
    expect(ButtonConfigValidator::errors([]))->toHaveKey('button_action');
    expect(ButtonConfigValidator::errors(['button_action' => 'bogus']))->toHaveKey('button_action');
});

test('workflow action requires a workflow id that is active and manually triggered', function () {
    expect(ButtonConfigValidator::errors(['button_action' => 'workflow']))->toHaveKey('button_workflow_id');

    $notManual = wfWorkflowWithVersion();
    WorkflowNode::factory()->for($notManual->currentVersion)->create(['type' => 'start', 'config' => ['trigger_type' => 'entity_created', 'entity_slug' => 'x']]);
    expect(ButtonConfigValidator::errors(['button_action' => 'workflow', 'button_workflow_id' => $notManual->id]))->toHaveKey('button_workflow_id');

    $manual = wfWorkflowWithVersion();
    WorkflowNode::factory()->for($manual->currentVersion)->start()->create();
    expect(ButtonConfigValidator::errors(['button_action' => 'workflow', 'button_workflow_id' => $manual->id]))->toBe([]);
});

test('importer action requires at least one importer id', function () {
    expect(ButtonConfigValidator::errors(['button_action' => 'importer']))->toHaveKey('button_importer_ids');
    expect(ButtonConfigValidator::errors(['button_action' => 'importer', 'button_importer_ids' => [3]]))->toBe([]);
});

test('javascript action requires non-empty code', function () {
    expect(ButtonConfigValidator::errors(['button_action' => 'javascript']))->toHaveKey('button_javascript');
    expect(ButtonConfigValidator::errors(['button_action' => 'javascript', 'button_javascript' => 'alert(1)']))->toBe([]);
});

test('parse builds the options array for each action', function () {
    expect(ButtonConfigValidator::parse(['button_action' => 'workflow', 'button_workflow_id' => '5']))->toBe([
        'button_action' => 'workflow',
        'button_workflow_id' => 5,
        'button_importer_ids' => [],
        'button_javascript' => null,
    ]);

    expect(ButtonConfigValidator::parse(['button_action' => 'importer', 'button_importer_ids' => [3, 7]]))->toBe([
        'button_action' => 'importer',
        'button_workflow_id' => null,
        'button_importer_ids' => [3, 7],
        'button_javascript' => null,
    ]);

    expect(ButtonConfigValidator::parse(['button_action' => 'javascript', 'button_javascript' => 'noop()']))->toBe([
        'button_action' => 'javascript',
        'button_workflow_id' => null,
        'button_importer_ids' => [],
        'button_javascript' => 'noop()',
    ]);
});

test('parse accepts a comma-separated string of importer ids, the structural builder\'s convention', function () {
    expect(ButtonConfigValidator::parse(['button_action' => 'importer', 'button_importer_ids' => '3, 7']))->toBe([
        'button_action' => 'importer',
        'button_workflow_id' => null,
        'button_importer_ids' => [3, 7],
        'button_javascript' => null,
    ]);
});
