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

test('"changed_to" is true only when the current value matches and the previous one differed', function () {
    $evaluator = new WorkflowConditionEvaluator;
    $rule = ['changed_to' => [['var' => 'entity.stato'], 'chiuso']];

    $entity = new stdClass;
    $entity->stato = 'chiuso';

    expect($evaluator->evaluate($rule, ['entity' => $entity, '__entity_previous' => ['stato' => 'aperto']]))->toBeTrue()
        ->and($evaluator->evaluate($rule, ['entity' => $entity, '__entity_previous' => ['stato' => 'chiuso']]))->toBeFalse();

    $entity->stato = 'aperto';
    expect($evaluator->evaluate($rule, ['entity' => $entity, '__entity_previous' => ['stato' => 'in_corso']]))->toBeFalse();
});

test('"changed_from" is true only when the previous value matches and the current one differs', function () {
    $evaluator = new WorkflowConditionEvaluator;
    $rule = ['changed_from' => [['var' => 'entity.stato'], 'aperto']];

    $entity = new stdClass;
    $entity->stato = 'chiuso';

    expect($evaluator->evaluate($rule, ['entity' => $entity, '__entity_previous' => ['stato' => 'aperto']]))->toBeTrue()
        ->and($evaluator->evaluate($rule, ['entity' => $entity, '__entity_previous' => ['stato' => 'in_corso']]))->toBeFalse();

    $entity->stato = 'aperto';
    expect($evaluator->evaluate($rule, ['entity' => $entity, '__entity_previous' => ['stato' => 'aperto']]))->toBeFalse();
});

test('"changed" is true whenever the previous and current values differ, whatever they are', function () {
    $evaluator = new WorkflowConditionEvaluator;
    $rule = ['changed' => [['var' => 'entity.stato']]];

    $entity = new stdClass;
    $entity->stato = 'chiuso';

    expect($evaluator->evaluate($rule, ['entity' => $entity, '__entity_previous' => ['stato' => 'aperto']]))->toBeTrue()
        ->and($evaluator->evaluate($rule, ['entity' => $entity, '__entity_previous' => ['stato' => 'chiuso']]))->toBeFalse();
});

test('"changed" treats a missing previous value as null, so any non-null current value counts as changed', function () {
    $evaluator = new WorkflowConditionEvaluator;
    $rule = ['changed' => [['var' => 'entity.stato']]];

    $entity = new stdClass;
    $entity->stato = 'aperto';

    expect($evaluator->evaluate($rule, ['entity' => $entity]))->toBeTrue();
});

test('a "changed" operator combines with normal operators inside and/or', function () {
    $evaluator = new WorkflowConditionEvaluator;
    $rule = ['and' => [
        ['changed_to' => [['var' => 'entity.stato'], 'chiuso']],
        ['>' => [['var' => 'entity.importo'], 100]],
    ]];

    $entity = new stdClass;
    $entity->stato = 'chiuso';
    $entity->importo = 150;

    expect($evaluator->evaluate($rule, ['entity' => $entity, '__entity_previous' => ['stato' => 'aperto']]))->toBeTrue();

    $entity->importo = 50;
    expect($evaluator->evaluate($rule, ['entity' => $entity, '__entity_previous' => ['stato' => 'aperto']]))->toBeFalse();
});

test('a "changed" operator on a var that is not an entity field is never considered changed', function () {
    $evaluator = new WorkflowConditionEvaluator;
    $rule = ['changed' => [['var' => 'importo']]];

    expect($evaluator->evaluate($rule, ['importo' => 150, '__entity_previous' => ['importo' => 50]]))->toBeFalse();
});
