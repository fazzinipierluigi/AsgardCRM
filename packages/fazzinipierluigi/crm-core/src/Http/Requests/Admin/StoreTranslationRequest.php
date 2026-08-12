<?php

namespace Fazzinipierluigi\CrmCore\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'key' => ['required', 'string', 'max:255', Rule::unique('translations', 'key')],
            'values' => ['array'],
            'values.*' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (collect($this->input('values', []))->filter()->isEmpty()) {
                $validator->errors()->add('values', 'Inserisci il valore per almeno una lingua.');
            }
        });
    }
}
