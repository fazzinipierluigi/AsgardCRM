<?php

namespace App\Http\Requests\Admin;

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Services\EntitySchemaBuilder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEntityBuilderRequest extends FormRequest
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
        return [
            'tabs' => ['required', 'array', 'min:1'],
            'tabs.*.id' => ['nullable', 'integer'],
            'tabs.*.name' => ['required', 'string', 'max:255'],
            'tabs.*.cards' => ['required', 'array', 'min:1'],
            'tabs.*.cards.*.id' => ['nullable', 'integer'],
            'tabs.*.cards.*.name' => ['required', 'string', 'max:255'],
            'tabs.*.cards.*.fields' => ['required', 'array', 'min:1'],
            'tabs.*.cards.*.fields.*.id' => ['nullable', 'integer'],
            'tabs.*.cards.*.fields.*.name' => ['required', 'string', 'max:255'],
            'tabs.*.cards.*.fields.*.column_name' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'tabs.*.cards.*.fields.*.type' => ['required', Rule::enum(EntityFieldType::class)],
            'tabs.*.cards.*.fields.*.options' => ['nullable', 'string'],
            'tabs.*.cards.*.fields.*.code_prefix' => ['nullable', 'string', 'max:50'],
            'tabs.*.cards.*.fields.*.relation_target' => ['nullable', 'string'],
            'tabs.*.cards.*.fields.*.required' => ['nullable', 'boolean'],
            'tabs.*.cards.*.fields.*.default_value' => ['nullable', 'string', 'max:255'],
            'tabs.*.cards.*.fields.*.width' => ['nullable', 'integer', 'between:1,12'],
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

            if ($entity->is_installed) {
                $validator->errors()->add('tabs', 'La struttura di un\'entità installata non è modificabile da qui.');

                return;
            }

            $seenColumnNames = [];

            foreach ($this->input('tabs', []) as $ti => $tab) {
                foreach (($tab['cards'] ?? []) as $ci => $card) {
                    foreach (($card['fields'] ?? []) as $fi => $field) {
                        $path = "tabs.{$ti}.cards.{$ci}.fields.{$fi}";
                        $column = $field['column_name'] ?? null;
                        $type = $field['type'] ?? null;

                        if ($column !== null) {
                            // A Relation field's real column is column_name+"_id" (see
                            // EntitySchemaBuilder) — check reservation/uniqueness against
                            // that physical name, not the one the admin typed, or two
                            // fields could collide on the actual dynamic table.
                            $physicalColumn = $type === EntityFieldType::Relation->value ? "{$column}_id" : $column;

                            if (in_array($physicalColumn, EntitySchemaBuilder::RESERVED_COLUMN_NAMES, true)) {
                                $validator->errors()->add("{$path}.column_name", 'Questo nome colonna è riservato.');
                            } elseif (isset($seenColumnNames[$physicalColumn])) {
                                $validator->errors()->add("{$path}.column_name", 'Nome colonna già usato in questa entità.');
                            }
                            $seenColumnNames[$physicalColumn] = true;
                        }

                        if ($type === EntityFieldType::Select->value && trim((string) ($field['options'] ?? '')) === '') {
                            $validator->errors()->add("{$path}.options", 'Le opzioni sono obbligatorie per un campo Select.');
                        }

                        if ($type === EntityFieldType::Relation->value && empty($field['relation_target'] ?? null)) {
                            $validator->errors()->add("{$path}.relation_target", 'Seleziona il target della relazione.');
                        }
                    }
                }
            }
        });
    }
}
