<?php

namespace Fazzinipierluigi\CrmCore\Services\Mail\Exchange;

use Fazzinipierluigi\CrmCore\Models\MailAccount;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailAttachmentDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailAttachmentSummaryDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailComposeDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailFolderDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailMessageDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailMessageSummaryDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\MailReaderInterface;
use Fazzinipierluigi\CrmCore\Services\Mail\MailSenderInterface;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * MailReaderInterface over Exchange Web Services — covers two cases:
 * a MailAccount with its own direct EWS credentials (no shared
 * MailConnector), or one on a shared EWS MailConnector, where the
 * connector's service-account credentials are used with
 * ExchangeImpersonation acting as the account's own email_address
 * (same "one service account, many mailboxes" idea as the calendar's
 * EwsExchangeConnector). $folder here is the EWS FolderId string
 * returned by findFolders(), an opaque per-account token exactly like
 * an IMAP path is — MailReaderInterface callers never need to know
 * the difference.
 */
class EwsMailClient implements MailReaderInterface, MailSenderInterface
{
    public function listFolders(MailAccount $account): array
    {
        $folders = $this->client($account)->findFolders($this->impersonateSmtp($account));

        return array_map(fn (array $folder) => new MailFolderDTO(
            path: $folder['id'],
            name: $folder['name'],
            hasChildren: $folder['hasChildren'],
            parentPath: $folder['parentId'],
        ), $folders);
    }

    public function listMessages(MailAccount $account, string $folder, int $page, int $perPage): array
    {
        $result = $this->client($account)->findMessages($folder, ($page - 1) * $perPage, $perPage, $this->impersonateSmtp($account));

        return [
            'items' => array_map($this->toSummary(...), $result['items']),
            'total' => $result['total'],
        ];
    }

    public function fetchMessage(MailAccount $account, string $folder, string $uid): MailMessageDTO
    {
        $message = $this->client($account)->getItem($uid, $this->impersonateSmtp($account));

        return $this->toFull($message);
    }

    public function fetchAttachment(MailAccount $account, string $folder, string $uid, string $attachmentId): MailAttachmentDTO
    {
        $attachment = $this->client($account)->getAttachment($attachmentId, $this->impersonateSmtp($account));

        return new MailAttachmentDTO(
            filename: $attachment['filename'],
            mimeType: $attachment['mimeType'],
            sizeBytes: strlen($attachment['content']),
            contents: $attachment['content'],
        );
    }

    public function send(MailAccount $account, MailComposeDTO $message): void
    {
        $this->client($account)->sendMessage(
            $message->subject,
            $message->bodyHtml,
            $message->to,
            $message->cc,
            $message->bcc,
            array_map(fn ($attachment) => ['filename' => $attachment->filename, 'mimeType' => $attachment->mimeType, 'contents' => $attachment->contents], $message->attachments),
            $message->inReplyTo,
            $this->impersonateSmtp($account),
        );
    }

    public function testConnection(MailAccount $account): array
    {
        try {
            $this->client($account)->ping($this->impersonateSmtp($account));

            return ['ok' => true, 'message' => 'Connessione riuscita.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function client(MailAccount $account): EwsMailSoapClient
    {
        $config = $account->usesSharedConnector() ? ($account->mailConnector->config ?? []) : ($account->config ?? []);

        return new EwsMailSoapClient($config);
    }

    /**
     * Only a shared connector needs impersonation — a MailAccount with
     * its own direct credentials logs into its own mailbox already.
     */
    private function impersonateSmtp(MailAccount $account): ?string
    {
        return $account->usesSharedConnector() ? $account->email_address : null;
    }

    private function toSummary(array $message): MailMessageSummaryDTO
    {
        return new MailMessageSummaryDTO(
            uid: $message['id'],
            subject: $message['subject'],
            fromAddress: $message['fromAddress'] ?: null,
            fromName: $message['fromName'] ?: null,
            date: $this->parseDate($message['dateTimeSent']),
            hasAttachments: $message['hasAttachments'],
            isRead: $message['isRead'],
        );
    }

    private function toFull(array $message): MailMessageDTO
    {
        $attachments = array_map(fn (array $attachment) => new MailAttachmentSummaryDTO(
            id: $attachment['id'],
            filename: $attachment['filename'],
            mimeType: $attachment['mimeType'],
            sizeBytes: $attachment['sizeBytes'],
        ), $message['attachments']);

        return new MailMessageDTO(
            uid: $message['id'],
            subject: $message['subject'],
            fromAddress: $message['fromAddress'] ?: null,
            fromName: $message['fromName'] ?: null,
            toAddresses: $message['toAddresses'],
            date: $this->parseDate($message['dateTimeSent']),
            messageId: $message['messageId'],
            textBody: null,
            htmlBody: $message['bodyHtml'] ?: null,
            attachments: $attachments,
        );
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
