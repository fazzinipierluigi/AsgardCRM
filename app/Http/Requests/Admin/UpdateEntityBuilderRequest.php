<?php

namespace App\Http\Requests\Admin;

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityField;
use App\Services\EntitySchemaBuilder;
use App\Support\ButtonConfigValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Two validation regimes depending on whether the entity is installed —
 * the payload shape (tabs/cards/fields tree) and the field-level rules
 * (column_name/type/relation_target format, per-type requirements) are
 * shared with the not-installed, whole-tree-replace flow on purpose (see
 * EntityBuilderController — same builder UI, same rules, uniform for the
 * admin either way). The difference is entirely about identity and
 * immutability once a physical column exists:
 *
 * Identity is carried by the array KEY the builder's own tokens use
 * (see resources/js/entity-builder.js's isExistingToken()) — a purely
 * numeric key is a real database id, anything else ("fnew1" etc.) is
 * brand new. There is never a nested `id` field in the payload itself
 * (the Blade partials only ever emit `[name]`, `[column_name]`, ... —
 * never `[id]`), so identity must always be read off the key, not off
 * `$field['id']`.
 *
 * - Not installed: nothing has a numeric key yet, everything is brand new.
 * - Installed (see EntityBuilderController::updateInstalled()): tabs/
 *   cards/fields may be existing (numeric key) or brand new (non-numeric
 *   key). An existing tab/card can be renamed; an existing field can only have
 *   its metadata changed (name/required/default_value/width/options/
 *   table_columns/button_*) — column_name/type/relation_target are
 *   rejected from the payload for it, since they can never change once
 *   the column is real. A brand new field is validated exactly like the
 *   not-installed flow (column_name/type/relation_target required,
 *   uniqueness checked against the entity's real existing columns too)
 *   and gets its column appended live. A new tab/card may legitimately
 *   have zero cards/fields at this point — see the plan for why
 *   requiring min:1 here would otherwise deadlock a freshly added card.
 */
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
        /** @var Entity $entity */
        $entity = $this->route('entity');

        if ($entity->is_installed) {
            return [
                'tabs' => ['required', 'array', 'min:1'],
                'tabs.*.name' => ['required', 'string', 'max:255'],
                'tabs.*.cards' => ['array'],
                'tabs.*.cards.*.name' => ['required', 'string', 'max:255'],
                'tabs.*.cards.*.fields' => ['array'],
                'tabs.*.cards.*.fields.*.name' => ['required', 'string', 'max:255'],
                'tabs.*.cards.*.fields.*.column_name' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
                'tabs.*.cards.*.fields.*.type' => ['nullable', Rule::enum(EntityFieldType::class)],
                'tabs.*.cards.*.fields.*.relation_target' => ['nullable', 'string'],
                'tabs.*.cards.*.fields.*.options' => ['nullable', 'string'],
                'tabs.*.cards.*.fields.*.code_prefix' => ['nullable', 'string', 'max:50'],
                'tabs.*.cards.*.fields.*.required' => ['nullable', 'boolean'],
                'tabs.*.cards.*.fields.*.default_value' => ['nullable', 'string', 'max:255'],
                'tabs.*.cards.*.fields.*.width' => ['nullable', 'integer', 'between:1,12'],
                'tabs.*.cards.*.fields.*.button_action' => ['nullable', Rule::in(['workflow', 'importer', 'javascript'])],
                'tabs.*.cards.*.fields.*.button_workflow_id' => ['nullable', 'integer'],
                'tabs.*.cards.*.fields.*.button_importer_ids' => ['nullable', 'string'],
                'tabs.*.cards.*.fields.*.button_javascript' => ['nullable', 'string'],
                'tabs.*.cards.*.fields.*.table_columns' => ['nullable', 'string'],
            ];
        }

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
            'tabs.*.cards.*.fields.*.button_action' => ['nullable', Rule::in(['workflow', 'importer', 'javascript'])],
            'tabs.*.cards.*.fields.*.button_workflow_id' => ['nullable', 'integer'],
            'tabs.*.cards.*.fields.*.button_importer_ids' => ['nullable', 'string'],
            'tabs.*.cards.*.fields.*.button_javascript' => ['nullable', 'string'],
            'tabs.*.cards.*.fields.*.table_columns' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        /** @var Entity $entity */
        $entity = $this->route('entity');

        if ($entity->is_installed) {
            $validator->after(fn (Validator $validator) => $this->validateInstalled($validator, $entity));

            return;
        }

        $validator->after(function (Validator $validator) {
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

                        if ($type === EntityFieldType::Button->value) {
                            foreach (ButtonConfigValidator::errors($field) as $errorField => $message) {
                                $validator->errors()->add("{$path}.{$errorField}", $message);
                            }
                        }

                        if ($type === EntityFieldType::Table->value && StoreEntityFieldRequest::parseTableColumns((string) ($field['table_columns'] ?? '')) === []) {
                            $validator->errors()->add("{$path}.table_columns", 'Definisci almeno una colonna valida per la tabella.');
                        }
                    }
                }
            }
        });
    }

    /**
     * For an existing field (has an `id`), the field's type is never
     * taken from the submitted payload (column_name/type/relation_target
     * aren't even accepted for it), always the one already stored in the
     * DB, since it's immutable post-install. A brand new field (no `id`)
     * is validated exactly like the not-installed whole-tree flow —
     * column_name/type/relation_target required, reserved/duplicate
     * column check — except uniqueness is checked against the entity's
     * real, already-installed columns too, not just siblings in this
     * same submit.
     */
    private function validateInstalled(Validator $validator, Entity $entity): void
    {
        $fieldIds = [];

        foreach ($this->input('tabs', []) as $tab) {
            foreach (($tab['cards'] ?? []) as $card) {
                foreach (($card['fields'] ?? []) as $fieldToken => $field) {
                    if (ctype_digit((string) $fieldToken)) {
                        $fieldIds[] = (int) $fieldToken;
                    }
                }
            }
        }

        $existingFields = EntityField::whereIn('id', $fieldIds)
            ->whereHas('card.tab', fn ($query) => $query->where('entity_id', $entity->id))
            ->get()
            ->keyBy('id');

        $seenColumnNames = $entity->allFields()
            ->mapWithKeys(fn (EntityField $field) => [
                ($field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name) => true,
            ])
            ->all();

        foreach ($this->input('tabs', []) as $ti => $tab) {
            foreach (($tab['cards'] ?? []) as $ci => $card) {
                foreach (($card['fields'] ?? []) as $fi => $field) {
                    $path = "tabs.{$ti}.cards.{$ci}.fields.{$fi}";

                    if (! ctype_digit((string) $fi)) {
                        $this->validateNewField($validator, $path, $field, $seenColumnNames);

                        continue;
                    }

                    $existing = $existingFields->get((int) $fi);

                    if (! $existing) {
                        $validator->errors()->add("{$path}.id", 'Campo non trovato su questa entità.');

                        continue;
                    }

                    $this->validateFieldTypeRules($validator, $path, $field, $existing->type->value);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, bool>  $seenColumnNames
     */
    private function validateNewField(Validator $validator, string $path, array $field, array &$seenColumnNames): void
    {
        $column = $field['column_name'] ?? null;
        $type = $field['type'] ?? null;

        if ($column === null || $type === null) {
            if ($column === null) {
                $validator->errors()->add("{$path}.column_name", 'Il nome colonna è obbligatorio per un nuovo campo.');
            }
            if ($type === null) {
                $validator->errors()->add("{$path}.type", 'Il tipo è obbligatorio per un nuovo campo.');
            }

            return;
        }

        $physicalColumn = $type === EntityFieldType::Relation->value ? "{$column}_id" : $column;

        if (in_array($physicalColumn, EntitySchemaBuilder::RESERVED_COLUMN_NAMES, true)) {
            $validator->errors()->add("{$path}.column_name", 'Questo nome colonna è riservato.');
        } elseif (isset($seenColumnNames[$physicalColumn])) {
            $validator->errors()->add("{$path}.column_name", 'Nome colonna già usato in questa entità.');
        }
        $seenColumnNames[$physicalColumn] = true;

        if ($type === EntityFieldType::Relation->value && empty($field['relation_target'] ?? null)) {
            $validator->errors()->add("{$path}.relation_target", 'Seleziona il target della relazione.');
        }

        $this->validateFieldTypeRules($validator, $path, $field, $type);
    }

    /**
     * Per-type requirements shared by both a brand new field and an
     * existing one having its metadata edited (Select needs options,
     * Button needs a valid action config, Table needs at least one valid
     * column definition).
     *
     * @param  array<string, mixed>  $field
     */
    private function validateFieldTypeRules(Validator $validator, string $path, array $field, string $type): void
    {
        if ($type === EntityFieldType::Select->value && trim((string) ($field['options'] ?? '')) === '') {
            $validator->errors()->add("{$path}.options", 'Le opzioni sono obbligatorie per un campo Select.');
        }

        if ($type === EntityFieldType::Button->value) {
            foreach (ButtonConfigValidator::errors($field) as $errorField => $message) {
                $validator->errors()->add("{$path}.{$errorField}", $message);
            }
        }

        if ($type === EntityFieldType::Table->value && StoreEntityFieldRequest::parseTableColumns((string) ($field['table_columns'] ?? '')) === []) {
            $validator->errors()->add("{$path}.table_columns", 'Definisci almeno una colonna valida per la tabella.');
        }
    }
}
