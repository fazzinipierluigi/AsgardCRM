<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Requests\Admin;

use Fazzinipierluigi\AsgardCRM\Enums\ImporterChannel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates just enough of the wizard's step 2/3 data (channel +
 * connection config) to attempt a preview() call — entity, field
 * mapping and scheduling aren't relevant yet at this point in the
 * wizard.
 */
class PreviewImporterRequest extends FormRequest
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
            'channel' => ['required', Rule::enum(ImporterChannel::class)],

            'driver' => ['required_if:channel,database', 'nullable', 'string', 'max:255'],
            'host' => ['required_if:channel,database', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:channel,database', 'nullable', 'integer'],
            'database' => ['required_if:channel,database', 'nullable', 'string', 'max:255'],
            'username' => ['required_if:channel,database', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'query' => ['required_if:channel,database', 'nullable', 'string'],

            'method' => ['required_if:channel,rest_api', 'nullable', 'string', 'max:10'],
            'endpoint' => ['required_if:channel,rest_api', 'nullable', 'url', 'max:2048'],
            'auth_type' => ['nullable', 'string', 'in:none,basic,bearer,api_key'],
            'auth_username' => ['nullable', 'string', 'max:255'],
            'auth_password' => ['nullable', 'string', 'max:255'],
            'auth_token' => ['nullable', 'string', 'max:255'],
            'auth_api_key_name' => ['nullable', 'string', 'max:255'],
            'auth_api_key_value' => ['nullable', 'string', 'max:255'],
            'params_json' => ['nullable', 'json'],

            'path_or_url' => ['required_if:channel,csv,json', 'nullable', 'string', 'max:2048'],
            'delimiter' => ['nullable', 'string', 'max:5'],
            'has_header' => ['boolean'],
        ];
    }
}
