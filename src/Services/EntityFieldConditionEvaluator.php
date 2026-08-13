<?php

namespace Fazzinipierluigi\AsgardCRM\Services;

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityField;
use JWadhams\JsonLogic;

/**
 * Server-side counterpart to resources/js/entity-field-conditions.js's
 * jsonLogicApply() — used only to keep a submission from 422ing when a
 * field is genuinely required (EntityField.required) but a currently
 * active condition hides it (see StoreEntityRecordRequest::rules()).
 * Everything else about a condition (readonly, required-when-visible)
 * stays a client-only UX affordance, per EntityFieldCondition's own
 * docblock — this evaluator exists purely to fix the one case that
 * would otherwise be a hard, broken-feature bug: a hidden field can
 * never be filled in, so it can't be allowed to block saving.
 *
 * A null/empty rule (no condition configured, or an empty and/or group
 * — same shape jsonlogic-editor-core can serialize) is treated as
 * "never active", not "always true": unlike WorkflowConditionEvaluator
 * (whose empty-rule-means-always-true convention fits "start unless
 * restricted"), a field condition with nothing configured shouldn't
 * silently hide/require fields no admin actually asked it to.
 */
class EntityFieldConditionEvaluator
{
    /**
     * The physical form column names of every field currently hidden
     * by at least one active condition on $entity, given the
     * submitted data (keyed by physical column name, same shape the
     * client evaluator reads off the form).
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public function hiddenColumns(Entity $entity, array $data): array
    {
        $hidden = [];

        foreach ($entity->fieldConditions()->with('targets.field')->get() as $condition) {
            if (! JsonLogic::truthy(JsonLogic::apply($condition->rule, $data))) {
                continue;
            }

            foreach ($condition->targets as $target) {
                if (! $target->visible) {
                    $hidden[] = $this->columnFor($target->field);
                }
            }
        }

        return array_values(array_unique($hidden));
    }

    private function columnFor(EntityField $field): string
    {
        return $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
    }
}
