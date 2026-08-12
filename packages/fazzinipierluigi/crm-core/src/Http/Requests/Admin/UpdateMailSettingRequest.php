<?php

namespace Fazzinipierluigi\CrmCore\Http\Requests\Admin;

use Fazzinipierluigi\CrmCore\Enums\MailAccountProtocol;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMailSettingRequest extends FormRequest
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
            'connection_timeout_seconds' => ['required', 'integer', 'min:1', 'max:120'],
            'max_attachment_size_kb' => ['required', 'integer', 'min:1'],
            'cache_ttl_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'enabled_protocols' => ['required', 'array', 'min:1'],
            'enabled_protocols.*' => [Rule::enum(MailAccountProtocol::class)],

            'google_oauth_client_id' => ['nullable', 'string', 'max:255'],
            'google_oauth_client_secret' => ['nullable', 'string', 'max:255'],
            'microsoft_oauth_client_id' => ['nullable', 'string', 'max:255'],
            'microsoft_oauth_client_secret' => ['nullable', 'string', 'max:255'],
        ];
    }
}
