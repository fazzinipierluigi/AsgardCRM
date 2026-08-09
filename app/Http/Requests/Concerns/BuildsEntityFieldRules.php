<?php

namespace App\Http\Requests\Concerns;

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityField;
use App\Rules\ProductsBlockRule;
use App\Rules\TableFieldRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Builds the validation rule (and physical column name) for a single
 * EntityField, per its type — shared by every request that validates a
 * submission against an entity's own field definitions rather than a
 * fixed set of columns (StoreEntityRecordRequest and the Calendar event
 * requests, which validate their six fixed fields the same dynamic way
 * since they're EntityFields too, see CalendarEntitySeeder).
 */
trait BuildsEntityFieldRules
{
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
            EntityFieldType::Table => ['nullable', new TableFieldRule($field->options['columns'] ?? [], $field->required)],
            EntityFieldType::ProductsBlock => ['nullable', new ProductsBlockRule(
                $field->options['extra_columns'] ?? [],
                $field->required,
                Entity::where('slug', $field->options['catalog_entity_slug'] ?? null)->value('table_name'),
            )],
        };
    }

    private function columnFor(EntityField $field): string
    {
        return $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
    }
}
