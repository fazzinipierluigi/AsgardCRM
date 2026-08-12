<?php

namespace Fazzinipierluigi\CrmCore\Http\Requests\Admin;

use Fazzinipierluigi\CrmCore\Enums\MailConnectorType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMailConnectorRequest extends FormRequest
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
            'type' => ['required', Rule::enum(MailConnectorType::class)],
            'is_active' => ['boolean'],

            'tenant_id' => ['required_if:type,exchange_graph', 'nullable', 'string', 'max:255'],
            'client_id' => ['required_if:type,exchange_graph', 'nullable', 'string', 'max:255'],
            'client_secret' => ['required_if:type,exchange_graph', 'nullable', 'string', 'max:255'],

            'ews_url' => ['required_if:type,exchange_ews', 'nullable', 'url', 'max:255'],
            'username' => ['required_if:type,exchange_ews', 'nullable', 'string', 'max:255'],
            'password' => ['required_if:type,exchange_ews', 'nullable', 'string', 'max:255'],
            'use_ntlm' => ['boolean'],
        ];
    }
}
