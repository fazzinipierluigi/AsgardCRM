<?php

namespace Fazzinipierluigi\CrmCore\Http\Requests;

use Fazzinipierluigi\CrmCore\Enums\MailAccountProtocol;
use Fazzinipierluigi\CrmCore\Enums\MailAuthMethod;
use Fazzinipierluigi\CrmCore\Enums\MailEncryption;
use Fazzinipierluigi\CrmCore\Models\MailAccount;
use Fazzinipierluigi\CrmCore\Models\MailConnector;
use Fazzinipierluigi\CrmCore\Models\MailSignature;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The account's protocol is immutable after creation, same as
 * Connector/MailConnector — the edit form carries it via a hidden
 * input. Password/secret fields are all nullable here: blank keeps
 * the previously stored value, see MailAccountController::update().
 * auth_method, unlike protocol, can be switched between Password and
 * an OAuth case at any time — MailAccountController::configFor()
 * rebuilds config from scratch on either side of that switch, so
 * there's nothing stale left over from the previous method.
 */
class UpdateMailAccountRequest extends FormRequest
{
    /**
     * Ownership check lives here, not in the controller body — form
     * request authorization runs before rules(), so a request for
     * someone else's account 403s even when its payload also happens
     * to fail validation (e.g. a bare {name} tamper attempt).
     */
    public function authorize(): bool
    {
        $mailAccount = $this->route('mailAccount');

        return $mailAccount instanceof MailAccount && $mailAccount->user_id === $this->user()?->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $requiresImapFieldset = fn () => $this->input('protocol') === MailAccountProtocol::Imap->value
            && $this->input('auth_method', MailAuthMethod::Password->value) === MailAuthMethod::Password->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'protocol' => ['required', Rule::enum(MailAccountProtocol::class)],
            'auth_method' => ['required', Rule::enum(MailAuthMethod::class)],
            'email_address' => ['required', 'email', 'max:255'],
            'is_active' => ['boolean'],
            'mail_connector_id' => ['nullable', Rule::exists(MailConnector::class, 'id')->where('is_active', true)],
            'mail_signature_id' => ['nullable', Rule::exists(MailSignature::class, 'id')],

            'imap_host' => [Rule::requiredIf($requiresImapFieldset), 'nullable', 'string', 'max:255'],
            'imap_port' => [Rule::requiredIf($requiresImapFieldset), 'nullable', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => [Rule::requiredIf($requiresImapFieldset), 'nullable', Rule::enum(MailEncryption::class)],
            'imap_username' => [Rule::requiredIf($requiresImapFieldset), 'nullable', 'string', 'max:255'],
            'imap_password' => ['nullable', 'string', 'max:255'],

            'pop3_host' => ['required_if:protocol,pop3', 'nullable', 'string', 'max:255'],
            'pop3_port' => ['required_if:protocol,pop3', 'nullable', 'integer', 'min:1', 'max:65535'],
            'pop3_encryption' => ['required_if:protocol,pop3', 'nullable', Rule::enum(MailEncryption::class)],
            'pop3_username' => ['required_if:protocol,pop3', 'nullable', 'string', 'max:255'],
            'pop3_password' => ['nullable', 'string', 'max:255'],

            'exchange_ews_url' => ['nullable', 'url', 'max:255'],
            'exchange_username' => ['nullable', 'string', 'max:255'],
            'exchange_password' => ['nullable', 'string', 'max:255'],
            'exchange_use_ntlm' => ['boolean'],

            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', Rule::enum(MailEncryption::class)],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $authMethod = MailAuthMethod::tryFrom((string) $this->input('auth_method')) ?? MailAuthMethod::Password;

            if ($authMethod !== MailAuthMethod::Password && $this->input('protocol') !== MailAccountProtocol::Imap->value) {
                $validator->errors()->add('auth_method', t('L\'autenticazione OAuth è disponibile solo per account IMAP.'));
            }
        });
    }
}
