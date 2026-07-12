<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoginProviderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['ldap', 'oauth', 'oidc', 'saml'])],
            'is_active' => ['boolean'],

            'host' => ['required_if:type,ldap', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:type,ldap', 'nullable', 'integer'],
            'base_dn' => ['required_if:type,ldap', 'nullable', 'string', 'max:255'],
            'bind_dn' => ['nullable', 'string', 'max:255'],
            'bind_password' => ['nullable', 'string', 'max:255'],
            'use_tls' => ['boolean'],
            'user_filter' => ['nullable', 'string', 'max:255'],
            'attr_username' => ['nullable', 'string', 'max:255'],
            'attr_email' => ['nullable', 'string', 'max:255'],
            'attr_name' => ['nullable', 'string', 'max:255'],

            'client_id' => ['required_if:type,oauth', 'required_if:type,oidc', 'nullable', 'string', 'max:255'],
            'client_secret' => ['required_if:type,oauth', 'required_if:type,oidc', 'nullable', 'string', 'max:255'],
            'authorize_url' => ['required_if:type,oauth', 'required_if:type,oidc', 'nullable', 'url', 'max:255'],
            'token_url' => ['required_if:type,oauth', 'required_if:type,oidc', 'nullable', 'url', 'max:255'],
            'userinfo_url' => ['required_if:type,oauth', 'required_if:type,oidc', 'nullable', 'url', 'max:255'],
            'scopes' => ['nullable', 'string', 'max:255'],

            'idp_entity_id' => ['required_if:type,saml', 'nullable', 'string', 'max:255'],
            'idp_sso_url' => ['required_if:type,saml', 'nullable', 'url', 'max:255'],
            'idp_x509_cert' => ['required_if:type,saml', 'nullable', 'string'],
            'sp_entity_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
