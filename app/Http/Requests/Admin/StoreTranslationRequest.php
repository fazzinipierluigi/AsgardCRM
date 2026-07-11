<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTranslationRequest extends FormRequest
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
            'key' => [
                'required', 'string', 'max:255',
                Rule::unique('translations')->where('language', $this->input('language')),
            ],
            'language' => ['required', 'string', Rule::in(array_keys(config('preferences.language.options')))],
            'value' => ['required', 'string'],
        ];
    }
}
