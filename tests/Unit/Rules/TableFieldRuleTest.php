<?php

use App\Rules\TableFieldRule;

/**
 * Runs the rule directly against a value, returning whether it passed
 * (no $fail call) — ValidationRule::validate() has no return value of
 * its own, it reports failure only by invoking the $fail closure.
 */
function tableFieldRulePasses(TableFieldRule $rule, mixed $value): bool
{
    $failed = false;
    $rule->validate('tabella', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

test('rows satisfying every required column pass', function () {
    $rule = new TableFieldRule([
        ['name' => 'qty', 'label' => 'Quantità', 'type' => 'integer', 'required' => true],
        ['name' => 'note', 'label' => 'Note', 'type' => 'string', 'required' => false],
    ], false);

    $value = json_encode([
        ['qty' => 5, 'note' => ''],
        ['qty' => 0, 'note' => 'ok'],
    ]);

    expect(tableFieldRulePasses($rule, $value))->toBeTrue();
});

test('a row missing a required column fails', function () {
    $rule = new TableFieldRule([
        ['name' => 'qty', 'label' => 'Quantità', 'type' => 'integer', 'required' => true],
    ], false);

    $value = json_encode([
        ['qty' => 5],
        ['qty' => ''],
    ]);

    expect(tableFieldRulePasses($rule, $value))->toBeFalse();
});

test('zero rows fails when the field itself is required', function () {
    $rule = new TableFieldRule([
        ['name' => 'qty', 'label' => 'Quantità', 'type' => 'integer', 'required' => true],
    ], true);

    expect(tableFieldRulePasses($rule, json_encode([])))->toBeFalse();
});

test('zero rows passes when the field itself is not required', function () {
    $rule = new TableFieldRule([
        ['name' => 'qty', 'label' => 'Quantità', 'type' => 'integer', 'required' => true],
    ], false);

    expect(tableFieldRulePasses($rule, json_encode([])))->toBeTrue();
});

test('a non-JSON value fails', function () {
    $rule = new TableFieldRule([], false);

    expect(tableFieldRulePasses($rule, 'not json'))->toBeFalse();
});

test('an already-decoded array value is accepted, not just a JSON string', function () {
    $rule = new TableFieldRule([
        ['name' => 'qty', 'label' => 'Quantità', 'type' => 'integer', 'required' => true],
    ], false);

    expect(tableFieldRulePasses($rule, [['qty' => 5]]))->toBeTrue()
        ->and(tableFieldRulePasses($rule, [['qty' => '']]))->toBeFalse();
});
