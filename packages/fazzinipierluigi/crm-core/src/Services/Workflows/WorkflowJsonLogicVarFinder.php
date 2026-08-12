<?php

namespace Fazzinipierluigi\CrmCore\Services\Workflows;

use JWadhams\JsonLogic;

/**
 * Recursively checks whether a JsonLogic rule tree contains a `{"var":
 * "<path>"}` node for a given exact path. Can't use
 * JsonLogic::add_operation() for this — custom operators registered that
 * way receive pre-evaluated arguments with no access to the raw tree —
 * so this walks the tree manually via JsonLogic's own
 * is_logic()/get_operator()/get_values() static helpers. Shared by
 * WorkflowFieldReferenceCleaner and WorkflowFieldReferenceScanner.
 */
class WorkflowJsonLogicVarFinder
{
    public function containsVar(mixed $rule, string $varPath): bool
    {
        if (! JsonLogic::is_logic($rule)) {
            if (is_array($rule)) {
                foreach ($rule as $item) {
                    if ($this->containsVar($item, $varPath)) {
                        return true;
                    }
                }
            }

            return false;
        }

        $operator = JsonLogic::get_operator($rule);
        $values = JsonLogic::get_values($rule);

        if ($operator === 'var') {
            return ($values[0] ?? null) === $varPath;
        }

        foreach ($values as $value) {
            if ($this->containsVar($value, $varPath)) {
                return true;
            }
        }

        return false;
    }
}
