<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Same fields as StoreWorkflowApiEndpointRequest except the secrets
 * (token/password/header_value) are always nullable — the controller
 * keeps the previously stored value when one is left blank on an edit
 * (same trick as ConnectorController/UpdateConnectorRequest).
 */
class UpdateWorkflowApiEndpointRequest extends FormRequest
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
            'workflow_id' => ['nullable', 'integer', Rule::exists('workflows', 'id')],
            'base_url' => ['required', 'string', 'max:255', 'regex:#^https?://#i'],
            'auth_type' => ['required', Rule::in(['none', 'bearer', 'basic', 'header'])],
            'token' => ['nullable', 'string', 'max:1000'],
            'username' => ['required_if:auth_type,basic', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'header_name' => ['required_if:auth_type,header', 'nullable', 'string', 'max:255'],
            'header_value' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
