<?php

namespace Fazzinipierluigi\CrmCore\Http\Requests\Admin;

use Fazzinipierluigi\CrmCore\Enums\MailConnectorType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The connector's type is immutable after creation, same as Connector
 * — the edit form carries it via a hidden input so required_if rules
 * still discriminate correctly, but the controller always persists the
 * type already stored on the model.
 */
class UpdateMailConnectorRequest extends FormRequest
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
            // Blank keeps the previously stored secret — see MailConnectorController::update().
            'client_secret' => ['nullable', 'string', 'max:255'],

            'ews_url' => ['required_if:type,exchange_ews', 'nullable', 'url', 'max:255'],
            'username' => ['required_if:type,exchange_ews', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'use_ntlm' => ['boolean'],
        ];
    }
}
