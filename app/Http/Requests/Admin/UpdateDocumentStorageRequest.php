<?php

namespace App\Http\Requests\Admin;

use App\Enums\DocumentStorageType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentStorageRequest extends FormRequest
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
            'type' => ['required', Rule::enum(DocumentStorageType::class)],

            'key' => ['required_if:type,s3', 'nullable', 'string', 'max:255'],
            // Blank keeps the previously stored secret — see DocumentStorageController::update().
            'secret' => ['nullable', 'string', 'max:255'],
            'region' => ['required_if:type,s3', 'nullable', 'string', 'max:255'],
            'bucket' => ['required_if:type,s3', 'nullable', 'string', 'max:255'],
            'endpoint' => ['nullable', 'url', 'max:255'],
            'use_path_style_endpoint' => ['boolean'],

            'ftp_host' => ['required_if:type,ftp', 'nullable', 'string', 'max:255'],
            'ftp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ftp_username' => ['required_if:type,ftp', 'nullable', 'string', 'max:255'],
            // Blank keeps the previously stored password — see DocumentStorageController::update().
            'ftp_password' => ['nullable', 'string', 'max:255'],
            'ftp_root' => ['nullable', 'string', 'max:255'],
            'ftp_ssl' => ['boolean'],

            'sftp_host' => ['required_if:type,sftp', 'nullable', 'string', 'max:255'],
            'sftp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'sftp_username' => ['required_if:type,sftp', 'nullable', 'string', 'max:255'],
            // Blank keeps the previously stored password — see DocumentStorageController::update().
            'sftp_password' => ['nullable', 'string', 'max:255'],
            'sftp_root' => ['nullable', 'string', 'max:255'],
        ];
    }
}
