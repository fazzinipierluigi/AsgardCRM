<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\BuildsEntityFieldRules;
use App\Models\Entity;
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
            if ($field->type->isGenerated()) {
                continue;
            }

            $rules[$this->columnFor($field)] = $this->rulesFor($field);
        }

        return $rules;
    }
}
