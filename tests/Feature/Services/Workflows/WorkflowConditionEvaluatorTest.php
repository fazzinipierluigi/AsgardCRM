<?php

use App\Services\Workflows\WorkflowConditionEvaluator;

test('a null or empty rule always evaluates to true', function () {
    $evaluator = new WorkflowConditionEvaluator;

    expect($evaluator->evaluate(null, ['importo' => 10]))->toBeTrue()
        ->and($evaluator->evaluate([], ['importo' => 10]))->toBeTrue();
});

test('an empty and/or group from the JSONLogicEditor UI also evaluates to true', function () {
    $evaluator = new WorkflowConditionEvaluator;

    expect($evaluator->evaluate(['and' => []], ['importo' => 10]))->toBeTrue()
        ->and($evaluator->evaluate(['or' => []], ['importo' => 10]))->toBeTrue();
});

test('evaluates a comparison rule against the context', function () {
    $evaluator = new WorkflowConditionEvaluator;
    $rule = ['>' => [['var' => 'importo'], 100]];

    expect($evaluator->evaluate($rule, ['importo' => 150]))->toBeTrue()
        ->and($evaluator->evaluate($rule, ['importo' => 50]))->toBeFalse();
});

test('reads a dotted var path off an object in the context, e.g. entity.campo', function () {
    $evaluator = new WorkflowConditionEvaluator;
    $rule = ['==' => [['var' => 'entity.stato'], 'aperto']];

    $entity = new stdClass;
    $entity->stato = 'aperto';

    expect($evaluator->evaluate($rule, ['entity' => $entity]))->toBeTrue();
});

test('evaluates a compound and/or rule', function () {
    $evaluator = new WorkflowConditionEvaluator;
    $rule = ['and' => [
        ['>' => [['var' => 'importo'], 100]],
        ['==' => [['var' => 'stato'], 'aperto']],
    ]];

    expect($evaluator->evaluate($rule, ['importo' => 150, 'stato' => 'aperto']))->toBeTrue()
        ->and($evaluator->evaluate($rule, ['importo' => 150, 'stato' => 'chiuso']))->toBeFalse();
});
