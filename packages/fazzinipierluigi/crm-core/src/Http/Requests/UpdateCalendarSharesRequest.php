<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCalendarSharesRequest extends FormRequest
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
            'shares' => ['nullable', 'array'],
            'shares.*' => ['required', Rule::in(['none', 'view', 'edit'])],
        ];
    }
}
