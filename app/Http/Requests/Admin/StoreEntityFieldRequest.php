<?php

namespace App\Http\Requests\Admin;

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityCard;
use App\Services\EntitySchemaBuilder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEntityFieldRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Entity $entity */
        $entity = $this->route('entity');

        return [
            'entity_card_id' => [
                'required',
                'integer',
                Rule::exists('entity_cards', 'id')->where(
                    fn ($query) => $query->whereIn('entity_tab_id', $entity->tabs()->pluck('id'))
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'column_name' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'type' => ['required', Rule::enum(EntityFieldType::class)],
            'options' => ['nullable', 'string'],
            'code_prefix' => ['nullable', 'string', 'max:50'],
            'relation_target' => ['nullable', 'string'],
            'required' => ['nullable', 'boolean'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'width' => ['nullable', 'integer', 'between:1,12'],
        ];
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Entity $entity */
            $entity = $this->route('entity');

            if (! $entity->is_installed) {
                $validator->errors()->add('entity_card_id', 'L\'entità deve essere installata prima di poterle aggiungere campi.');

                return;
            }

            $column = $this->input('column_name');
            $type = $this->input('type');

            if ($column !== null) {
                $physicalColumn = $type === EntityFieldType::Relation->value ? "{$column}_id" : $column;
                $existingColumns = $entity->allFields()
                    ->map(fn ($field) => $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name);

                if (in_array($physicalColumn, EntitySchemaBuilder::RESERVED_COLUMN_NAMES, true)) {
                    $validator->errors()->add('column_name', 'Questo nome colonna è riservato.');
                } elseif ($existingColumns->contains($physicalColumn)) {
                    $validator->errors()->add('column_name', 'Nome colonna già usato in questa entità.');
                }
            }

            if ($type === EntityFieldType::Select->value && trim((string) $this->input('options', '')) === '') {
                $validator->errors()->add('options', 'Le opzioni sono obbligatorie per un campo Select.');
            }

            if ($type === EntityFieldType::Relation->value && empty($this->input('relation_target'))) {
                $validator->errors()->add('relation_target', 'Seleziona il target della relazione.');
            }
        });
    }

    /**
     * The card this field is being added to, resolved after validation
     * confirms it belongs to the entity.
     */
    public function card(): EntityCard
    {
        return EntityCard::findOrFail($this->validated('entity_card_id'));
    }
}
