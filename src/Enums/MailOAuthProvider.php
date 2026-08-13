<?php

namespace Fazzinipierluigi\AsgardCRM\Enums;

/**
 * A delegated-OAuth mailbox provider — endpoints, scope and the
 * well-known IMAP/SMTP host+port a MailAccount authenticating this way
 * connects to (see Fazzinipierluigi\AsgardCRM\Services\Mail\OAuth\MailOAuthService and
 * ImapMailReader/SmtpMailSender's own oauth branches). Deliberately
 * separate from MailAuthMethod: this enum only knows protocol-level
 * constants, never a MailAccount/MailSetting — MailOAuthService is
 * where client_id/client_secret (admin-configured, see MailSetting)
 * get attached to these. Adding a further provider later (Yahoo,
 * Fastmail, ...) means one more case here plus its client id/secret in
 * MailSetting — nothing else in the OAuth flow is provider-specific.
 */
enum MailOAuthProvider: string
{
    case Google = 'google';
    case Microsoft = 'microsoft';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Microsoft => 'Microsoft 365',
        };
    }

    public function authorizeUrl(): string
    {
        return match ($this) {
            self::Google => 'https://accounts.google.com/o/oauth2/v2/auth',
            self::Microsoft => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
        };
    }

    public function tokenUrl(): string
    {
        return match ($this) {
            self::Google => 'https://oauth2.googleapis.com/token',
            self::Microsoft => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
        };
    }

    /**
     * IMAP.AccessAsUser.All/SMTP.Send are Microsoft's delegated Outlook
     * protocol scopes; offline_access is what makes it hand back a
     * refresh_token at all (without it the access token is a one-shot,
     * ~1h token with no way to renew it short of another consent
     * screen). Google always returns a refresh_token on the first
     * consent regardless of scope, but only when the authorize request
     * also carries access_type=offline — see MailOAuthService::authorizeUrl().
     */
    public function scope(): string
    {
        return match ($this) {
            self::Google => 'https://mail.google.com/',
            self::Microsoft => 'offline_access https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send',
        };
    }

    public function imapHost(): string
    {
        return match ($this) {
            self::Google => 'imap.gmail.com',
            self::Microsoft => 'outlook.office365.com',
        };
    }

    public function imapPort(): int
    {
        return 993;
    }

    public function smtpHost(): string
    {
        return match ($this) {
            self::Google => 'smtp.gmail.com',
            self::Microsoft => 'smtp.office365.com',
        };
    }

    public function smtpPort(): int
    {
        return match ($this) {
            self::Google => 465,
            self::Microsoft => 587,
        };
    }

    public function smtpEncryption(): MailEncryption
    {
        return match ($this) {
            self::Google => MailEncryption::Ssl,
            self::Microsoft => MailEncryption::StartTls,
        };
    }
}
