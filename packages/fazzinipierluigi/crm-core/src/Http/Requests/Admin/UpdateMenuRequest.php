<?php

namespace Fazzinipierluigi\CrmCore\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuRequest extends FormRequest
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
        $installedEntity = Rule::exists('entities', 'id')->where('is_installed', true);

        return [
            'visible' => ['array'],
            'visible.*' => ['integer', $installedEntity],
            'quick_access' => ['array'],
            'quick_access.*' => ['integer', $installedEntity],
        ];
    }
}
