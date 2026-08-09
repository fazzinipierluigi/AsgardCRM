<?php

namespace App\Services\Mail\Exchange;

use App\Models\MailAccount;
use App\Services\Mail\DTO\MailAttachmentDTO;
use App\Services\Mail\DTO\MailAttachmentSummaryDTO;
use App\Services\Mail\DTO\MailComposeDTO;
use App\Services\Mail\DTO\MailFolderDTO;
use App\Services\Mail\DTO\MailMessageDTO;
use App\Services\Mail\DTO\MailMessageSummaryDTO;
use App\Services\Mail\MailReaderInterface;
use App\Services\Mail\MailSenderInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * MailReaderInterface over Microsoft Graph — only used when a
 * MailAccount is on a shared MailConnector of type ExchangeGraph
 * (see MailAccount::usesSharedConnector()); a direct-credentials
 * Exchange account never reaches this class. Every call targets
 * `/users/{account->email_address}/...` using the connector's
 * app-only token (MailGraphTokenClient), the same "app-only reaches
 * any tenant mailbox" idea the calendar's GraphExchangeConnector uses.
 */
class GraphMailClient implements MailReaderInterface, MailSenderInterface
{
    private const BASE_URL = 'https://graph.microsoft.com/v1.0';

    public function __construct(private readonly MailGraphTokenClient $tokenClient) {}

    /**
     * Real mailboxes never nest more than a handful of levels deep —
     * this is purely a circuit breaker against a malformed/malicious
     * response (or a future Graph quirk) reporting childFolderCount>0
     * on a folder that then reports itself again, which would
     * otherwise recurse without end.
     */
    private const MAX_FOLDER_DEPTH = 20;

    public function listFolders(MailAccount $account): array
    {
        return $this->fetchFolders($account, self::BASE_URL."/users/{$account->email_address}/mailFolders", null, 0);
    }

    /**
     * Graph has no single "flat, deep" folder listing (unlike EWS's
     * Traversal="Deep") — nested folders only come back through a
     * separate childFolders call per parent, so this walks the tree
     * one level at a time and flattens it into MailFolderDTO's own
     * parentPath links.
     *
     * @return list<MailFolderDTO>
     */
    private function fetchFolders(MailAccount $account, string $url, ?string $parentPath, int $depth): array
    {
        if ($depth >= self::MAX_FOLDER_DEPTH) {
            return [];
        }

        $response = $this->client($account)->get($url, ['$top' => 100]);
        $this->assertSuccessful($response);

        $folders = [];

        foreach ($response->json('value') ?? [] as $folder) {
            $hasChildren = ($folder['childFolderCount'] ?? 0) > 0;

            $folders[] = new MailFolderDTO(
                path: $folder['id'],
                name: $folder['displayName'] ?? '',
                hasChildren: $hasChildren,
                parentPath: $parentPath,
            );

            if ($hasChildren) {
                $childUrl = self::BASE_URL."/users/{$account->email_address}/mailFolders/{$folder['id']}/childFolders";
                array_push($folders, ...$this->fetchFolders($account, $childUrl, $folder['id'], $depth + 1));
            }
        }

        return $folders;
    }

    public function listMessages(MailAccount $account, string $folder, int $page, int $perPage): array
    {
        $response = $this->client($account)->withHeaders(['ConsistencyLevel' => 'eventual'])
            ->get(self::BASE_URL."/users/{$account->email_address}/mailFolders/{$folder}/messages", [
                '$top' => $perPage,
                '$skip' => ($page - 1) * $perPage,
                '$count' => 'true',
                '$select' => 'subject,from,sentDateTime,hasAttachments,isRead',
            ]);
        $this->assertSuccessful($response);

        // Response::json('@odata.count') would misfire — Laravel's json()
        // helper treats the argument as dot-notation, and this key's own
        // "." is literal, not a nesting separator.
        return [
            'items' => array_map($this->toSummary(...), $response->json('value') ?? []),
            'total' => (int) ($response->json()['@odata.count'] ?? 0),
        ];
    }

    public function fetchMessage(MailAccount $account, string $folder, string $uid): MailMessageDTO
    {
        $response = $this->client($account)->get(self::BASE_URL."/users/{$account->email_address}/messages/{$uid}", [
            '$select' => 'subject,from,toRecipients,sentDateTime,body,hasAttachments,internetMessageId',
            '$expand' => 'attachments($select=id,name,contentType,size)',
        ]);
        $this->assertSuccessful($response);

        return $this->toFull($response->json());
    }

    public function fetchAttachment(MailAccount $account, string $folder, string $uid, string $attachmentId): MailAttachmentDTO
    {
        $response = $this->client($account)->get(self::BASE_URL."/users/{$account->email_address}/messages/{$uid}/attachments/{$attachmentId}");
        $this->assertSuccessful($response);

        $attachment = $response->json();
        $content = (string) base64_decode((string) ($attachment['contentBytes'] ?? ''), true);

        return new MailAttachmentDTO(
            filename: $attachment['name'] ?? 'allegato',
            mimeType: $attachment['contentType'] ?? null,
            sizeBytes: strlen($content),
            contents: $content,
        );
    }

    public function send(MailAccount $account, MailComposeDTO $message): void
    {
        $payload = [
            'message' => [
                'subject' => $message->subject,
                'body' => ['contentType' => 'HTML', 'content' => $message->bodyHtml],
                'toRecipients' => $this->toRecipients($message->to),
                'ccRecipients' => $this->toRecipients($message->cc),
                'bccRecipients' => $this->toRecipients($message->bcc),
                'attachments' => array_map(fn ($attachment) => [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $attachment->filename,
                    'contentType' => $attachment->mimeType,
                    'contentBytes' => base64_encode($attachment->contents),
                ], $message->attachments),
            ],
            'saveToSentItems' => true,
        ];

        if ($message->inReplyTo !== null) {
            $payload['message']['internetMessageHeaders'] = [
                ['name' => 'In-Reply-To', 'value' => $message->inReplyTo],
            ];
        }

        $response = $this->client($account)->post(self::BASE_URL."/users/{$account->email_address}/sendMail", $payload);
        $this->assertSuccessful($response);
    }

    public function testConnection(MailAccount $account): array
    {
        try {
            $response = $this->client($account)->get(self::BASE_URL."/users/{$account->email_address}/mailFolders", ['$top' => 1]);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return $response->successful()
            ? ['ok' => true, 'message' => 'Connessione riuscita.']
            : ['ok' => false, 'message' => "Connessione fallita ({$response->status()}): {$response->body()}"];
    }

    /**
     * @param  list<string>  $addresses
     * @return list<array<string, mixed>>
     */
    private function toRecipients(array $addresses): array
    {
        return array_map(fn (string $address) => ['emailAddress' => ['address' => $address]], $addresses);
    }

    private function client(MailAccount $account): PendingRequest
    {
        $connector = $account->mailConnector;

        if ($connector === null) {
            throw new RuntimeException('Questa casella non è collegata a un connector Exchange aziendale.');
        }

        return Http::withToken($this->tokenClient->tokenFor($connector));
    }

    private function assertSuccessful(Response $response): void
    {
        if ($response->failed()) {
            throw new RuntimeException("Errore Microsoft Graph ({$response->status()}): {$response->body()}");
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function toSummary(array $message): MailMessageSummaryDTO
    {
        $from = $message['from']['emailAddress'] ?? [];

        return new MailMessageSummaryDTO(
            uid: $message['id'],
            subject: $message['subject'] ?? '',
            fromAddress: $from['address'] ?? null,
            fromName: $from['name'] ?? null,
            date: $this->parseDate($message['sentDateTime'] ?? null),
            hasAttachments: (bool) ($message['hasAttachments'] ?? false),
            isRead: (bool) ($message['isRead'] ?? true),
        );
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function toFull(array $message): MailMessageDTO
    {
        $from = $message['from']['emailAddress'] ?? [];
        $toAddresses = array_map(fn (array $recipient) => $recipient['emailAddress']['address'] ?? '', $message['toRecipients'] ?? []);

        $attachments = array_map(fn (array $attachment) => new MailAttachmentSummaryDTO(
            id: $attachment['id'],
            filename: $attachment['name'] ?? 'allegato',
            mimeType: $attachment['contentType'] ?? null,
            sizeBytes: $attachment['size'] ?? null,
        ), $message['attachments'] ?? []);

        return new MailMessageDTO(
            uid: $message['id'],
            subject: $message['subject'] ?? '',
            fromAddress: $from['address'] ?? null,
            fromName: $from['name'] ?? null,
            toAddresses: array_values(array_filter($toAddresses)),
            date: $this->parseDate($message['sentDateTime'] ?? null),
            messageId: $message['internetMessageId'] ?? null,
            textBody: null,
            htmlBody: $message['body']['content'] ?? null,
            attachments: $attachments,
        );
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
