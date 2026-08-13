<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
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
        return collect(preferences())
            ->mapWithKeys(fn (array $preference, string $key) => [
                $key => ['required', Rule::in(array_keys($preference['options']))],
            ])
            ->all();
    }
}
