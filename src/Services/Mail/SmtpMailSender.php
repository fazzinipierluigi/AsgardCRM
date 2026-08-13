<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Mail;

use Fazzinipierluigi\AsgardCRM\Enums\MailAuthMethod;
use Fazzinipierluigi\AsgardCRM\Enums\MailEncryption;
use Fazzinipierluigi\AsgardCRM\Mail\ComposedMail;
use Fazzinipierluigi\AsgardCRM\Models\MailAccount;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailComposeDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\OAuth\MailOAuthService;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * MailSenderInterface over SMTP, for IMAP/POP3/EWS-direct accounts —
 * registers a per-account ephemeral Laravel mailer at send() time
 * (never a static config/mail.php entry), same runtime-config trick
 * Fazzinipierluigi\AsgardCRM\Services\DocumentStorageDiskResolver uses for a storage-backend
 * disk. The mailer name is scoped to the account id, so concurrent
 * sends from different accounts never clash. Sends through a proper
 * Mailable (Fazzinipierluigi\AsgardCRM\Mail\ComposedMail) rather than Mailer::html()'s raw
 * closure form — Mail::fake() (used by the test suite) only reliably
 * intercepts Mailable-based sends, not the raw form.
 *
 * An OAuth account (see MailAuthMethod) needs no transport-level
 * change at all: Symfony's EsmtpTransport already tries XOAUTH2 among
 * its default authenticators whenever the server advertises it,
 * falling back to it automatically once CRAM-MD5/LOGIN/PLAIN all fail
 * against a token-as-password — registerMailer() only has to supply
 * the provider's own host/port and the fresh access token as the
 * "password".
 */
class SmtpMailSender implements MailSenderInterface
{
    public function send(MailAccount $account, MailComposeDTO $message): void
    {
        Mail::mailer($this->registerMailer($account))->send(new ComposedMail($account, $message));
    }

    public function testConnection(MailAccount $account): array
    {
        try {
            Mail::mailer($this->registerMailer($account))->getSymfonyTransport()->start();

            return ['ok' => true, 'message' => 'Connessione riuscita.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function registerMailer(MailAccount $account): string
    {
        $mailerName = "mail-account-{$account->id}";

        config(["mail.mailers.{$mailerName}" => $account->auth_method === MailAuthMethod::Password
            ? $this->passwordMailerConfig($account)
            : $this->oauthMailerConfig($account)]);

        return $mailerName;
    }

    /**
     * @return array<string, mixed>
     */
    private function passwordMailerConfig(MailAccount $account): array
    {
        $config = $account->config ?? [];
        $encryption = MailEncryption::tryFrom((string) ($config['smtp_encryption'] ?? 'starttls')) ?? MailEncryption::StartTls;

        return [
            'transport' => 'smtp',
            'host' => $config['smtp_host'] ?? '',
            'port' => (int) ($config['smtp_port'] ?? 587),
            'encryption' => $encryption === MailEncryption::None ? null : $encryption->value,
            'username' => $config['smtp_username'] ?? '',
            'password' => $config['smtp_password'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function oauthMailerConfig(MailAccount $account): array
    {
        $provider = $account->auth_method->provider();
        $encryption = $provider->smtpEncryption();
        $token = (new MailOAuthService)->freshAccessToken($account);

        return [
            'transport' => 'smtp',
            'host' => $provider->smtpHost(),
            'port' => $provider->smtpPort(),
            'encryption' => $encryption === MailEncryption::None ? null : $encryption->value,
            'username' => $account->email_address,
            'password' => $token,
        ];
    }
}
