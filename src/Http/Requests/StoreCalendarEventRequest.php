<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Requests;

use Fazzinipierluigi\AsgardCRM\Http\Requests\Concerns\BuildsEntityFieldRules;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a calendar event submission against the Calendar entity's
 * own field definitions — its six fixed fields are EntityFields like any
 * other (see CalendarEntitySeeder), plus any custom field a user has
 * appended via EntityFieldController, so the rules are built the exact
 * same dynamic way as StoreEntityRecordRequest. The polymorphic
 * relatable_type/relatable_id pair is added on top since it isn't an
 * EntityField — it's hardcoded onto every calendar entity's table by
 * EntitySchemaBuilder.
 */
class StoreCalendarEventRequest extends FormRequest
{
    use BuildsEntityFieldRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $entity = Entity::where('slug', 'calendario')->firstOrFail();

        $rules = [];

        foreach ($entity->allFields() as $field) {
            if ($field->type->isGenerated()) {
                continue;
            }

            $rules[$this->columnFor($field)] = $this->rulesFor($field);
        }

        $rules['relatable_type'] = ['nullable', 'string'];
        $rules['relatable_id'] = ['nullable', 'integer'];

        return $rules;
    }
}
