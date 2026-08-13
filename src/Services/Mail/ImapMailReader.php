<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Mail;

use Carbon\CarbonImmutable;
use Fazzinipierluigi\AsgardCRM\Enums\MailAuthMethod;
use Fazzinipierluigi\AsgardCRM\Enums\MailEncryption;
use Fazzinipierluigi\AsgardCRM\Models\MailAccount;
use Fazzinipierluigi\AsgardCRM\Models\MailSetting;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailAttachmentDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailAttachmentSummaryDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailFolderDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailMessageDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailMessageSummaryDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\OAuth\MailOAuthService;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

/**
 * MailReaderInterface over webklex/php-imap — every call opens its own
 * connection, does exactly the one thing it was asked, and disconnects;
 * nothing here is cached beyond the single request (see the interface's
 * own docblock and MailController's short-lived listing cache).
 */
class ImapMailReader implements MailReaderInterface
{
    public function listFolders(MailAccount $account): array
    {
        $client = $this->connect($account);

        try {
            return $client->getFolders()->map(fn ($folder) => new MailFolderDTO(
                path: $folder->path,
                name: $folder->name,
                hasChildren: $folder->hasChildren(),
                parentPath: $this->parentPath($folder->path, $folder->delimiter),
            ))->values()->all();
        } finally {
            $client->disconnect();
        }
    }

    public function listMessages(MailAccount $account, string $folder, int $page, int $perPage): array
    {
        $client = $this->connect($account);

        try {
            $query = $client->getFolderByPath($folder)->messages()->all();
            $paginator = $query->paginate($perPage, $page);

            $items = collect($paginator->items())->map(fn (Message $message) => $this->toSummary($message))->values()->all();

            return ['items' => $items, 'total' => $paginator->total()];
        } finally {
            $client->disconnect();
        }
    }

    public function fetchMessage(MailAccount $account, string $folder, string $uid): MailMessageDTO
    {
        $client = $this->connect($account);

        try {
            $message = $client->getFolderByPath($folder)->query()->getMessageByUid((int) $uid);

            return $this->toFull($message);
        } finally {
            $client->disconnect();
        }
    }

    public function fetchAttachment(MailAccount $account, string $folder, string $uid, string $attachmentId): MailAttachmentDTO
    {
        $client = $this->connect($account);

        try {
            $message = $client->getFolderByPath($folder)->query()->getMessageByUid((int) $uid);
            $attachment = $message->getAttachments()->first(fn ($a) => (string) $a->part_number === $attachmentId);

            if ($attachment === null) {
                throw new RuntimeException("Attachment {$attachmentId} not found on message {$uid}.");
            }

            return new MailAttachmentDTO(
                filename: (string) ($attachment->name ?? $attachment->filename ?? $attachmentId),
                mimeType: $attachment->content_type,
                sizeBytes: (int) ($attachment->size ?? strlen((string) $attachment->content)),
                contents: (string) $attachment->content,
            );
        } finally {
            $client->disconnect();
        }
    }

    public function testConnection(MailAccount $account): array
    {
        try {
            $client = $this->connect($account);
            $client->disconnect();

            return ['ok' => true, 'message' => 'Connessione riuscita.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function connect(MailAccount $account): Client
    {
        return (new ClientManager)->make($this->clientConfig($account))->connect();
    }

    /**
     * Maps a MailAccount's decrypted config into webklex/php-imap's own
     * account config shape — split out from connect() so it's testable
     * without opening a real socket (ClientManager::make() itself is
     * side-effect-free; only the ->connect() call after it touches the
     * network).
     *
     * @return array<string, mixed>
     */
    public function clientConfig(MailAccount $account): array
    {
        if ($account->auth_method !== MailAuthMethod::Password) {
            return $this->oauthClientConfig($account);
        }

        $config = $account->config ?? [];
        $encryption = MailEncryption::tryFrom((string) ($config['encryption'] ?? 'ssl')) ?? MailEncryption::Ssl;

        return [
            'host' => $config['host'] ?? '',
            'port' => (int) ($config['port'] ?? 993),
            'protocol' => 'imap',
            'encryption' => $encryption === MailEncryption::None ? false : $encryption->value,
            'validate_cert' => true,
            'username' => $config['username'] ?? '',
            'password' => $config['password'] ?? '',
            'timeout' => MailSetting::current()->connection_timeout_seconds,
        ];
    }

    /**
     * Host/port are never user-entered for an OAuth account (see
     * MailAuthMethod/mail/accounts/_form.blade.php) — they come
     * straight from MailOAuthProvider's own well-known constants. The
     * "password" webklex/php-imap authenticates with here is really a
     * short-lived access token, refreshed on demand by MailOAuthService
     * — see Client::authenticate()'s own "oauth" branch, which sends it
     * as SASL XOAUTH2 rather than a plaintext LOGIN.
     */
    private function oauthClientConfig(MailAccount $account): array
    {
        $provider = $account->auth_method->provider();
        $token = (new MailOAuthService)->freshAccessToken($account);

        return [
            'host' => $provider->imapHost(),
            'port' => $provider->imapPort(),
            'protocol' => 'imap',
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => $account->email_address,
            'password' => $token,
            'authentication' => 'oauth',
            'timeout' => MailSetting::current()->connection_timeout_seconds,
        ];
    }

    private function toSummary(Message $message): MailMessageSummaryDTO
    {
        $from = $message->getFrom()->first();

        return new MailMessageSummaryDTO(
            uid: (string) $message->getUid(),
            subject: (string) $message->getSubject(),
            fromAddress: $from?->mail,
            fromName: $from?->personal,
            date: $this->toCarbon($message),
            hasAttachments: $message->hasAttachments(),
            isRead: $message->getFlags()->has('seen'),
        );
    }

    private function toFull(Message $message): MailMessageDTO
    {
        $from = $message->getFrom()->first();
        $to = collect($message->getTo()->toArray())->map(fn ($address) => $address->mail)->filter()->values()->all();

        $attachments = $message->getAttachments()->map(fn ($attachment) => new MailAttachmentSummaryDTO(
            id: (string) $attachment->part_number,
            filename: (string) ($attachment->name ?? $attachment->filename ?? 'allegato'),
            mimeType: $attachment->content_type,
            sizeBytes: $attachment->size !== null ? (int) $attachment->size : null,
        ))->values()->all();

        return new MailMessageDTO(
            uid: (string) $message->getUid(),
            subject: (string) $message->getSubject(),
            fromAddress: $from?->mail,
            fromName: $from?->personal,
            toAddresses: $to,
            date: $this->toCarbon($message),
            messageId: $message->getMessageId(),
            textBody: $message->hasTextBody() ? $message->getTextBody() : null,
            htmlBody: $message->hasHTMLBody() ? $message->getHTMLBody() : null,
            attachments: $attachments,
        );
    }

    /**
     * IMAP has no separate "parent id" concept — a folder's own
     * dotted/slashed path already encodes its position (e.g.
     * "INBOX.Archive.2024" under delimiter "."), so the parent is
     * simply that path with its last segment removed.
     */
    private function parentPath(string $path, string $delimiter): ?string
    {
        if (! str_contains($path, $delimiter)) {
            return null;
        }

        $segments = explode($delimiter, $path);
        array_pop($segments);

        return implode($delimiter, $segments);
    }

    private function toCarbon(Message $message): ?CarbonImmutable
    {
        if (! $message->getDate()->has()) {
            return null;
        }

        return CarbonImmutable::instance($message->getDate()->toDate());
    }
}
