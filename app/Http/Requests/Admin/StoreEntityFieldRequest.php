<?php

namespace App\Http\Requests\Admin;

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityCard;
use App\Services\EntitySchemaBuilder;
use App\Support\ButtonConfigValidator;
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
            'button_action' => ['required_if:type,button', Rule::in(['workflow', 'importer', 'javascript'])],
            'button_workflow_id' => ['nullable', 'integer'],
            'button_importer_ids' => ['nullable', 'array'],
            'button_importer_ids.*' => ['integer', Rule::exists('importers', 'id')],
            'button_javascript' => ['nullable', 'string'],
            'table_columns' => ['required_if:type,table', 'string'],
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

            if ($type === EntityFieldType::Button->value) {
                foreach (ButtonConfigValidator::errors($this->all()) as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            }

            if ($type === EntityFieldType::Table->value && self::parseTableColumns((string) $this->input('table_columns', '')) === []) {
                $validator->errors()->add('table_columns', 'Definisci almeno una colonna valida per la tabella.');
            }
        });
    }

    /**
     * Parses the "one column per line" textarea format shared by the
     * add-field form and the pre-install structural builder:
     * `nome_colonna:Etichetta:tipo:obbligatoria`, tipo one of
     * string|integer|decimal|date|checkbox (default string),
     * obbligatoria si/1/true (default no).
     *
     * @return list<array{name: string, label: string, type: string, required: bool}>
     */
    public static function parseTableColumns(string $raw): array
    {
        $allowedTypes = ['string', 'integer', 'decimal', 'date', 'checkbox'];
        $columns = [];

        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$name, $label, $type, $required] = array_pad(explode(':', $line, 4), 4, null);
            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            $type = trim((string) $type);
            $type = in_array($type, $allowedTypes, true) ? $type : 'string';

            $columns[] = [
                'name' => $name,
                'label' => trim((string) ($label ?? $name)) ?: $name,
                'type' => $type,
                'required' => in_array(mb_strtolower(trim((string) $required)), ['si', '1', 'true'], true),
            ];
        }

        return $columns;
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
