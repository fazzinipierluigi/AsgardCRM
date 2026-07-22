<?php

namespace App\Services\Workflows;

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
 */
class WorkflowConditionEvaluator
{
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

        return JsonLogic::truthy(JsonLogic::apply($rule, $context));
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
