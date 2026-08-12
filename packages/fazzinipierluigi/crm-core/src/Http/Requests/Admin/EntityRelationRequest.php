<?php

namespace Fazzinipierluigi\CrmCore\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by store() and update() of Admin\EntityRelationController.
 * entity_b_id is the "other" entity of the pair — entity_a_id is
 * always the {entity} route parameter, never user input.
 */
class EntityRelationRequest extends FormRequest
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
            'entity_b_id' => [
                'required',
                Rule::exists('entities', 'id')->where('is_installed', true),
                Rule::notIn([(int) $this->route('entity')->id]),
            ],
        ];
    }
}
