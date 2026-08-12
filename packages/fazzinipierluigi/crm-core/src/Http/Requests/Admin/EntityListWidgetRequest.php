<?php

namespace Fazzinipierluigi\CrmCore\Http\Requests\Admin;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Support\ButtonConfigValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared by both store() and update() of EntityListWidgetController — a
 * list widget has no append-only/whole-tree distinction like entity
 * fields do, so one request class covers creating and editing.
 */
class EntityListWidgetRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['button', 'counter', 'chart'])],
            'position' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'button_action' => ['required_if:type,button', Rule::in(['workflow', 'importer', 'javascript'])],
            'button_workflow_id' => ['nullable', 'integer'],
            'button_importer_ids' => ['nullable', 'array'],
            'button_importer_ids.*' => ['integer', Rule::exists('importers', 'id')],
            'button_javascript' => ['nullable', 'string'],
            'counter_color' => ['nullable', 'string', 'max:50'],
            'counter_icon' => ['nullable', 'string', 'max:100'],
            'chart_type' => ['required_if:type,chart', Rule::in(['bar', 'line', 'pie', 'doughnut'])],
            'chart_group_by' => ['required_if:type,chart', 'string'],
            'chart_aggregate' => ['required_if:type,chart', Rule::in(['count', 'sum', 'avg'])],
            'chart_value_column' => ['nullable', 'string'],
            'filter_column' => ['nullable', 'string'],
            'filter_operator' => ['nullable', Rule::in(['=', '!=', '>', '<', '>=', '<='])],
            'filter_value' => ['nullable', 'string'],
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
            $type = $this->input('type');
            $columns = self::filterableColumns($entity);
            $numericColumns = self::numericColumns($entity);

            if ($type === 'button') {
                foreach (ButtonConfigValidator::errors($this->all()) as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            }

            if ($type === 'chart') {
                $groupBy = $this->input('chart_group_by');

                if ($groupBy !== null && ! array_key_exists($groupBy, $columns)) {
                    $validator->errors()->add('chart_group_by', 'Colonna non valida.');
                }

                if ($this->input('chart_aggregate') !== 'count') {
                    $valueColumn = $this->input('chart_value_column');

                    if (empty($valueColumn)) {
                        $validator->errors()->add('chart_value_column', 'Seleziona la colonna numerica da aggregare.');
                    } elseif (! array_key_exists($valueColumn, $numericColumns)) {
                        $validator->errors()->add('chart_value_column', 'Colonna non valida per questa aggregazione.');
                    }
                }
            }

            $filterColumn = $this->input('filter_column');

            if (! empty($filterColumn)) {
                if (! array_key_exists($filterColumn, $columns)) {
                    $validator->errors()->add('filter_column', 'Colonna non valida.');
                }

                if (empty($this->input('filter_operator'))) {
                    $validator->errors()->add('filter_operator', 'Seleziona un operatore.');
                }

                if ($this->input('filter_value') === null || $this->input('filter_value') === '') {
                    $validator->errors()->add('filter_value', 'Il valore del filtro è obbligatorio.');
                }
            }
        });
    }

    /**
     * The filter/group-by JSON blob shared by counter and chart widgets.
     *
     * @return array{column: string, operator: string, value: string}|null
     */
    public function filterConfig(): ?array
    {
        $column = $this->input('filter_column');

        if (empty($column)) {
            return null;
        }

        return [
            'column' => $column,
            'operator' => $this->input('filter_operator'),
            'value' => $this->input('filter_value'),
        ];
    }

    /**
     * Every real, physical column of the entity a filter/group-by can
     * target — everything except Button (no column at all) and Table
     * (a JSON blob, not a scalar to compare/group by), keyed by column
     * name with the field's own name as label.
     *
     * @return array<string, string>
     */
    public static function filterableColumns(Entity $entity): array
    {
        return $entity->allFields()
            ->reject(fn ($field) => in_array($field->type, [EntityFieldType::Button, EntityFieldType::Table, EntityFieldType::ProductsBlock], true))
            ->mapWithKeys(fn ($field) => [self::columnFor($field) => $field->name])
            ->all();
    }

    /**
     * The subset of filterableColumns() that holds a number — the only
     * columns a chart's sum/avg aggregate can target.
     *
     * @return array<string, string>
     */
    public static function numericColumns(Entity $entity): array
    {
        return $entity->allFields()
            ->filter(fn ($field) => in_array($field->type, [EntityFieldType::IntegerNumber, EntityFieldType::DecimalNumber], true))
            ->mapWithKeys(fn ($field) => [self::columnFor($field) => $field->name])
            ->all();
    }

    private static function columnFor(mixed $field): string
    {
        return $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
    }
}
