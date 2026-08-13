<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by store() and update() of Admin\EntityFieldConditionController.
 * `rule` arrives as a JSON string (the hidden input the JSONLogicEditor
 * mount serializes its tree into — see admin/entities/conditions/form.blade.php)
 * rather than a nested array, since a JsonLogic tree's shape is
 * entirely rule-defined and not something Laravel's array validation
 * rules can usefully describe.
 */
class EntityFieldConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'rule' => ['nullable', 'string'],
            'fields' => ['nullable', 'array'],
            'fields.*.managed' => ['nullable', 'boolean'],
            'fields.*.visible' => ['nullable', 'boolean'],
            'fields.*.readonly' => ['nullable', 'boolean'],
            'fields.*.required' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The submitted rule, decoded — null for an empty/absent/invalid
     * JsonLogic tree (treated as "always true" by the client evaluator,
     * same convention as WorkflowConditionEvaluator).
     */
    public function decodedRule(): ?array
    {
        $raw = $this->validated('rule');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }
}
