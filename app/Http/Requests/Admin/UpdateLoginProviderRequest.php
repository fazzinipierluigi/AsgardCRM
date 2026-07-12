<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoginProviderRequest extends FormRequest
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
     * The provider's type is immutable after creation — the edit form
     * carries it via a hidden input so `required_if` rules below still
     * discriminate correctly, but the controller always persists the
     * type already stored on the model, never the submitted value.
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
            // Blank keeps the previously stored secret — see LoginProviderController::update().
            'bind_password' => ['nullable', 'string', 'max:255'],
            'use_tls' => ['boolean'],
            'user_filter' => ['nullable', 'string', 'max:255'],
            'attr_username' => ['nullable', 'string', 'max:255'],
            'attr_email' => ['nullable', 'string', 'max:255'],
            'attr_name' => ['nullable', 'string', 'max:255'],

            'client_id' => ['required_if:type,oauth', 'required_if:type,oidc', 'nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
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
