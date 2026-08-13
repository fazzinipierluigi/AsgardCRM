<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Requests;

use Fazzinipierluigi\AsgardCRM\Http\Requests\Concerns\BuildsEntityFieldRules;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Services\EntityFieldConditionEvaluator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEntityRecordRequest extends FormRequest
{
    use BuildsEntityFieldRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Build validation rules from the entity's own field definitions —
     * there's no fixed set of fields, it depends entirely on which
     * entity the route resolved.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Entity $entity */
        $entity = $this->route('entity');

        $rules = [];

        foreach ($entity->allFields() as $field) {
            // Generated fields (Code) are never submitted by the user —
            // their value comes from EntityCodeGenerator, not the request.
            // Action fields (Button) never hold a value at all.
            if ($field->type->isGenerated() || $field->type->isAction() || $field->is_hidden) {
                continue;
            }

            $rules[$this->columnFor($field)] = $this->rulesFor($field);
        }

        // A field can be genuinely required (EntityField.required) yet
        // currently hidden by an active EntityFieldCondition — it can
        // never be filled in while hidden, so it can't be allowed to
        // block saving. See EntityFieldConditionEvaluator's own
        // docblock for why this is the one piece of condition logic
        // that *is* enforced server-side.
        $hiddenColumns = app(EntityFieldConditionEvaluator::class)->hiddenColumns($entity, $this->all());

        foreach ($hiddenColumns as $column) {
            if (isset($rules[$column][0]) && $rules[$column][0] === 'required') {
                $rules[$column][0] = 'nullable';
            }
        }

        return $rules;
    }
}
