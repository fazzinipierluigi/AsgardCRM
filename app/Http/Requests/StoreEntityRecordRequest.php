<?php

namespace App\Http\Requests;

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityField;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntityRecordRequest extends FormRequest
{
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

    /**
     * @return array<int, string|ValidationRule>
     */
    private function rulesFor(EntityField $field): array
    {
        $required = $field->required ? 'required' : 'nullable';

        return match ($field->type) {
            EntityFieldType::Checkbox => ['nullable', 'boolean'],
            EntityFieldType::String, EntityFieldType::ColorPicker => [$required, 'string', 'max:255'],
            EntityFieldType::Select => [$required, Rule::in(array_keys($field->options ?? []))],
            EntityFieldType::IntegerNumber => [$required, 'integer'],
            EntityFieldType::DecimalNumber => [$required, 'numeric'],
            EntityFieldType::Textarea, EntityFieldType::RichText => [$required, 'string'],
            EntityFieldType::Relation => [$required, 'integer'],
            EntityFieldType::Date => [$required, 'date'],
            EntityFieldType::Time => [$required, 'date_format:H:i'],
            EntityFieldType::DateTime => [$required, 'date'],
        };
    }

    private function columnFor(EntityField $field): string
    {
        return $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
    }
}
