<?php

namespace Fazzinipierluigi\CrmCore\Http\Requests;

use Fazzinipierluigi\CrmCore\Enums\MailAccountProtocol;
use Fazzinipierluigi\CrmCore\Enums\MailAuthMethod;
use Fazzinipierluigi\CrmCore\Enums\MailEncryption;
use Fazzinipierluigi\CrmCore\Models\MailConnector;
use Fazzinipierluigi\CrmCore\Models\MailSignature;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a self-service mailbox account. Field names for the IMAP/
 * POP3/Exchange-direct/SMTP fieldsets are prefixed (imap_host,
 * pop3_host, exchange_ews_url, smtp_host, ...) to avoid id/name
 * collisions between fieldsets coexisting on the same page — same
 * convention as UpdateDocumentStorageRequest's ftp_/sftp_ prefixes —
 * stripped back to plain keys for storage in
 * MailAccountController::configFor(). The imap_* fieldset is only
 * required when auth_method is Password — an OAuth account (see
 * MailAuthMethod) needs none of it, host/port come from
 * MailOAuthProvider's own constants instead.
 */
class StoreMailAccountRequest extends FormRequest
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
            'imap_password' => [Rule::requiredIf($requiresImapFieldset), 'nullable', 'string', 'max:255'],

            'pop3_host' => ['required_if:protocol,pop3', 'nullable', 'string', 'max:255'],
            'pop3_port' => ['required_if:protocol,pop3', 'nullable', 'integer', 'min:1', 'max:65535'],
            'pop3_encryption' => ['required_if:protocol,pop3', 'nullable', Rule::enum(MailEncryption::class)],
            'pop3_username' => ['required_if:protocol,pop3', 'nullable', 'string', 'max:255'],
            'pop3_password' => ['required_if:protocol,pop3', 'nullable', 'string', 'max:255'],

            // Only required for a direct Exchange account (protocol=exchange
            // and no mail_connector_id chosen) — enforced in withValidator()
            // below, since "required unless a sibling field is also set" has
            // no single declarative rule.
            'exchange_ews_url' => ['nullable', 'url', 'max:255'],
            'exchange_username' => ['nullable', 'string', 'max:255'],
            'exchange_password' => ['nullable', 'string', 'max:255'],
            'exchange_use_ntlm' => ['boolean'],

            // SMTP (sending) — required for imap/pop3 accounts that don't
            // use a shared connector; Exchange accounts always send
            // through EWS/Graph instead, never SMTP.
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
            $protocol = $this->input('protocol');
            $authMethod = MailAuthMethod::tryFrom((string) $this->input('auth_method')) ?? MailAuthMethod::Password;
            $usesConnector = $this->filled('mail_connector_id');
            $usesOAuth = $authMethod !== MailAuthMethod::Password;

            if ($usesOAuth && $protocol !== MailAccountProtocol::Imap->value) {
                $validator->errors()->add('auth_method', t('L\'autenticazione OAuth è disponibile solo per account IMAP.'));
            }

            if ($protocol === MailAccountProtocol::Exchange->value && ! $usesConnector) {
                foreach (['exchange_ews_url', 'exchange_username', 'exchange_password'] as $field) {
                    if (! $this->filled($field)) {
                        $validator->errors()->add($field, t('Campo obbligatorio senza un connector aziendale selezionato.'));
                    }
                }
            }

            // An OAuth account sends through the same token it reads
            // with (see SmtpMailSender::oauthMailerConfig()) — it has no
            // smtp_* fieldset of its own to fill in.
            if (! $usesOAuth && in_array($protocol, [MailAccountProtocol::Imap->value, MailAccountProtocol::Pop3->value], true)) {
                foreach (['smtp_host', 'smtp_username', 'smtp_password'] as $field) {
                    if (! $this->filled($field)) {
                        $validator->errors()->add($field, t('Campo obbligatorio per inviare posta da questa casella.'));
                    }
                }
            }
        });
    }
}
