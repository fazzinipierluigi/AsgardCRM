<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConnectorMailboxesRequest extends FormRequest
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
            'mailboxes' => ['nullable', 'array'],
            'mailboxes.*' => ['nullable', 'email', 'max:255'],
        ];
    }
}
