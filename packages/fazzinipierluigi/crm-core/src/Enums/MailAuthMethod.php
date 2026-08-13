<?php

namespace Fazzinipierluigi\AsgardCRM\Enums;

/**
 * How a MailAccount proves its identity to the mail server — a plain
 * stored password (the only option before this enum existed), or a
 * delegated OAuth2 flow against one of MailOAuthProvider's cases. Only
 * meaningful for protocol=imap: OAuth is wired into ImapMailReader/
 * SmtpMailSender, and a direct Exchange/POP3 account has no OAuth path
 * of its own (Exchange already has its own auth story via
 * MailConnector) — see StoreMailAccountRequest's validation.
 */
enum MailAuthMethod: string
{
    case Password = 'password';
    case GoogleOAuth = 'google_oauth';
    case MicrosoftOAuth = 'microsoft_oauth';

    public function label(): string
    {
        return match ($this) {
            self::Password => 'Password',
            self::GoogleOAuth => 'Google (OAuth)',
            self::MicrosoftOAuth => 'Microsoft 365 (OAuth)',
        };
    }

    public function provider(): ?MailOAuthProvider
    {
        return match ($this) {
            self::Password => null,
            self::GoogleOAuth => MailOAuthProvider::Google,
            self::MicrosoftOAuth => MailOAuthProvider::Microsoft,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $method) => [$method->value => $method->label()])->all();
    }
}
