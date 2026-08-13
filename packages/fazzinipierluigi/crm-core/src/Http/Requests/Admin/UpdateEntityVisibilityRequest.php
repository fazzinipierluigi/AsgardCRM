<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Requests\Admin;

use Fazzinipierluigi\AsgardCRM\Enums\EntityVisibilityLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntityVisibilityRequest extends FormRequest
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
            'levels' => ['required', 'array'],
            'levels.*' => ['required', Rule::enum(EntityVisibilityLevel::class)],
        ];
    }
}
