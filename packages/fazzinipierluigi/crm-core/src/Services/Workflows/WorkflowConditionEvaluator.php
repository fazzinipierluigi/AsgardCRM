<?php

namespace Fazzinipierluigi\CrmCore\Services\Workflows;

use JWadhams\JsonLogic;

/**
 * Evaluates the JsonLogic rules used specifically for *conditions* — a
 * start node's start_condition and an exclusive gate's edge
 * condition_logic — as opposed to the free-form value expressions
 * (Symfony ExpressionLanguage, see WorkflowExpressionEvaluator) used
 * everywhere else an action computes a value.
 *
 * jwadhams/json-logic-php's `var` accessor already understands both
 * plain arrays and objects (via property access), so the same context
 * array WorkflowActionExecutor::buildContext() builds — variables plus
 * an `entity` Eloquent model — works unchanged here: a rule like
 * {"var": "entity.totale"} reads $entity->totale through Eloquent's
 * magic __get/__isset.
 *
 * Before handing the rule to jwadhams/json-logic-php, expandChangeOperators()
 * rewrites any "è cambiato" node (changed_to/changed_from/changed — see
 * resources/js/workflow-builder.js's changeOperatorDefs()) into a plain
 * true/false literal. This can't be a json-logic-php custom operation
 * (registered via JsonLogic::add_operation()): those receive their
 * arguments already evaluated and have no access to $context, but a
 * "did this field change" check needs the *unevaluated* field path (to
 * look up its previous value) as well as its current one. Walking the
 * raw rule ourselves first sidesteps that limitation entirely.
 */
class WorkflowConditionEvaluator
{
    /**
     * @var list<string>
     */
    private const CHANGE_OPERATORS = ['changed_to', 'changed_from', 'changed'];

    /**
     * @param  mixed  $rule  A JsonLogic rule (array), or null/empty to
     *                       mean "always true" (no condition set).
     * @param  array<string, mixed>  $context
     */
    public function evaluate(mixed $rule, array $context = []): bool
    {
        if ($this->isEmptyRule($rule)) {
            return true;
        }

        $rule = $this->expandChangeOperators($rule, $context);

        return JsonLogic::truthy(JsonLogic::apply($rule, $context));
    }

    /**
     * Recursively rewrites every changed_to/changed_from/changed node
     * into a true/false literal, leaving every other operator (and,
     * or, ==, ...) structurally untouched for jwadhams/json-logic-php
     * to evaluate as usual.
     *
     * @param  array<string, mixed>  $context
     */
    private function expandChangeOperators(mixed $rule, array $context): mixed
    {
        if (! JsonLogic::is_logic($rule)) {
            if (is_array($rule)) {
                return array_map(fn ($item) => $this->expandChangeOperators($item, $context), $rule);
            }

            return $rule;
        }

        $operator = JsonLogic::get_operator($rule);
        $values = JsonLogic::get_values($rule);

        if (in_array($operator, self::CHANGE_OPERATORS, true)) {
            return $this->evaluateChangeOperator($operator, $values, $context);
        }

        return [$operator => array_map(fn ($value) => $this->expandChangeOperators($value, $context), $values)];
    }

    /**
     * @param  array<int, mixed>  $args
     * @param  array<string, mixed>  $context
     */
    private function evaluateChangeOperator(string $operator, array $args, array $context): bool
    {
        $field = $this->entityFieldFromArg($args[0] ?? null);

        if ($field === null) {
            return false;
        }

        $current = data_get($context, "entity.{$field}");
        $previous = data_get($context, "__entity_previous.{$field}");

        return match ($operator) {
            'changed' => $current != $previous,
            'changed_to' => $current == $this->resolveTarget($args[1] ?? null, $context) && $previous != $current,
            'changed_from' => $previous == $this->resolveTarget($args[1] ?? null, $context) && $current != $previous,
        };
    }

    /**
     * The entity field name out of a raw (unevaluated) {"var": "entity.campo"}
     * argument — null for anything else, including a var pointing at a
     * plain workflow variable rather than an entity field.
     */
    private function entityFieldFromArg(mixed $arg): ?string
    {
        if (! is_array($arg) || ! array_key_exists('var', $arg) || ! is_string($arg['var'])) {
            return null;
        }

        return str_starts_with($arg['var'], 'entity.') ? substr($arg['var'], strlen('entity.')) : null;
    }

    private function resolveTarget(mixed $arg, array $context): mixed
    {
        return JsonLogic::is_logic($arg) ? JsonLogic::apply($arg, $context) : $arg;
    }

    /**
     * True for null/''/[] and also for an empty "and"/"or" group, e.g.
     * {"and": []} — what the JSONLogicEditor UI serializes when the
     * user adds an empty rule group and then removes every row from
     * it, rather than deleting the group itself. jwadhams/json-logic-
     * php has no identity value for a 0-operand and/or (it errors and
     * returns null, i.e. false), so left unhandled this would silently
     * turn "no condition" into "never starts".
     */
    private function isEmptyRule(mixed $rule): bool
    {
        if ($rule === null || $rule === [] || $rule === '') {
            return true;
        }

        if (is_array($rule) && count($rule) === 1) {
            $operator = array_key_first($rule);
            $operands = $rule[$operator];

            if (in_array($operator, ['and', 'or'], true) && $operands === []) {
                return true;
            }
        }

        return false;
    }
}
