<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Mail;

use Carbon\CarbonImmutable;
use Fazzinipierluigi\AsgardCRM\Enums\MailEncryption;
use Fazzinipierluigi\AsgardCRM\Models\MailAccount;
use Fazzinipierluigi\AsgardCRM\Models\MailSetting;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailAttachmentDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailAttachmentSummaryDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailFolderDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailMessageDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailMessageSummaryDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\Pop3\Pop3Client;
use Fazzinipierluigi\AsgardCRM\Services\Mail\Pop3\Pop3MessageParser;
use RuntimeException;
use Throwable;

/**
 * MailReaderInterface over a raw POP3 connection (Pop3Client +
 * Pop3MessageParser). POP3 has no server-side folder concept — every
 * account only ever has the single synthetic "INBOX" folder (see
 * MailAccountProtocol::hasFolders()) — and, unlike IMAP, a "uid" here
 * is only stable if the server supports the optional UIDL command;
 * when it doesn't, message numbers are used instead, which can shift
 * if messages are deleted between two calls.
 */
class Pop3MailReader implements MailReaderInterface
{
    private const FOLDER = 'INBOX';

    public function __construct(private readonly Pop3MessageParser $parser = new Pop3MessageParser) {}

    public function listFolders(MailAccount $account): array
    {
        return [new MailFolderDTO(self::FOLDER, 'Posta in arrivo')];
    }

    public function listMessages(MailAccount $account, string $folder, int $page, int $perPage): array
    {
        $client = $this->connect($account);

        try {
            $total = $client->stat()['count'];
            $uidsByNumber = $this->uidsByNumber($client, $total);
            $numbers = array_slice(range(1, $total), ($page - 1) * $perPage, $perPage);

            $items = array_map(function (int $number) use ($client, $uidsByNumber) {
                $headers = $this->parser->parseHeaders($client->retrieveHeaders($number));

                return $this->toSummary($headers, $uidsByNumber[$number] ?? (string) $number);
            }, $numbers);

            return ['items' => $items, 'total' => $total];
        } finally {
            $client->quit();
        }
    }

    public function fetchMessage(MailAccount $account, string $folder, string $uid): MailMessageDTO
    {
        $client = $this->connect($account);

        try {
            $number = $this->resolveMessageNumber($client, $uid);
            $raw = $client->retrieve($number);

            return $this->toFull($raw, $uid);
        } finally {
            $client->quit();
        }
    }

    public function fetchAttachment(MailAccount $account, string $folder, string $uid, string $attachmentId): MailAttachmentDTO
    {
        $client = $this->connect($account);

        try {
            $number = $this->resolveMessageNumber($client, $uid);
            $raw = $client->retrieve($number);
            [$rawHeaders, $rawBody] = $this->splitMessage($raw);
            $headers = $this->parser->parseHeaders($rawHeaders);
            $parsed = $this->parser->parseBody($headers, $rawBody);

            $index = (int) $attachmentId;
            $attachment = $parsed['attachments'][$index] ?? null;

            if ($attachment === null) {
                throw new RuntimeException("Attachment {$attachmentId} not found on message {$uid}.");
            }

            return new MailAttachmentDTO(
                filename: $attachment['filename'],
                mimeType: $attachment['mimeType'],
                sizeBytes: strlen($attachment['content']),
                contents: $attachment['content'],
            );
        } finally {
            $client->quit();
        }
    }

    public function testConnection(MailAccount $account): array
    {
        try {
            $client = $this->connect($account);
            $client->quit();

            return ['ok' => true, 'message' => 'Connessione riuscita.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function connect(MailAccount $account): Pop3Client
    {
        $config = $account->config ?? [];
        $encryption = MailEncryption::tryFrom((string) ($config['encryption'] ?? 'ssl')) ?? MailEncryption::Ssl;

        $client = new Pop3Client;
        $client->connect(
            $config['host'] ?? '',
            (int) ($config['port'] ?? 995),
            $encryption,
            MailSetting::current()->connection_timeout_seconds,
        );
        $client->login($config['username'] ?? '', $config['password'] ?? '');

        return $client;
    }

    /**
     * @return array<int, string>
     */
    private function uidsByNumber(Pop3Client $client, int $total): array
    {
        $uidl = $client->uidl();

        if ($uidl !== []) {
            return $uidl;
        }

        // No UIDL support: fall back to the message number itself as a
        // best-effort identifier (see this class's own docblock).
        return array_combine(range(1, $total), array_map('strval', range(1, $total)));
    }

    private function resolveMessageNumber(Pop3Client $client, string $uid): int
    {
        $uidl = $client->uidl();
        $number = array_search($uid, $uidl, true);

        return $number !== false ? $number : (int) $uid;
    }

    private function toSummary(array $headers, string $uid): MailMessageSummaryDTO
    {
        [$fromAddress, $fromName] = $this->splitAddress($headers['from'] ?? null);

        return new MailMessageSummaryDTO(
            uid: $uid,
            subject: $headers['subject'] ?? '',
            fromAddress: $fromAddress,
            fromName: $fromName,
            date: $this->parseDate($headers['date'] ?? null),
            hasAttachments: false, // Unknown without downloading the full body — see fetchMessage() for the real count.
            isRead: true, // POP3 has no per-message "seen" flag.
        );
    }

    private function toFull(string $raw, string $uid): MailMessageDTO
    {
        [$rawHeaders, $rawBody] = $this->splitMessage($raw);
        $headers = $this->parser->parseHeaders($rawHeaders);
        $parsed = $this->parser->parseBody($headers, $rawBody);
        [$fromAddress, $fromName] = $this->splitAddress($headers['from'] ?? null);

        $toAddresses = isset($headers['to'])
            ? array_map(fn ($address) => trim($this->splitAddress($address)[0] ?? ''), explode(',', $headers['to']))
            : [];

        $attachments = array_map(fn ($index, $attachment) => new MailAttachmentSummaryDTO(
            id: (string) $index,
            filename: $attachment['filename'],
            mimeType: $attachment['mimeType'],
            sizeBytes: strlen($attachment['content']),
        ), array_keys($parsed['attachments']), $parsed['attachments']);

        return new MailMessageDTO(
            uid: $uid,
            subject: $headers['subject'] ?? '',
            fromAddress: $fromAddress,
            fromName: $fromName,
            toAddresses: array_values(array_filter($toAddresses)),
            date: $this->parseDate($headers['date'] ?? null),
            messageId: $headers['message-id'] ?? null,
            textBody: $parsed['textBody'],
            htmlBody: $parsed['htmlBody'],
            attachments: $attachments,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitMessage(string $raw): array
    {
        $split = preg_split("/\r?\n\r?\n/", $raw, 2);

        return [$split[0] ?? '', $split[1] ?? ''];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitAddress(?string $header): array
    {
        if ($header === null) {
            return [null, null];
        }

        if (preg_match('/^(.*)<(.+)>$/', trim($header), $matches)) {
            return [trim($matches[2]), trim($matches[1], " \t\"") ?: null];
        }

        return [trim($header), null];
    }

    private function parseDate(?string $header): ?CarbonImmutable
    {
        if ($header === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($header);
        } catch (Throwable) {
            return null;
        }
    }
}
