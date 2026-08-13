<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Mail\Testing;

use Fazzinipierluigi\AsgardCRM\Models\MailAccount;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailAttachmentDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailFolderDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailMessageDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailMessageSummaryDTO;
use Fazzinipierluigi\AsgardCRM\Services\Mail\MailReaderInterface;
use RuntimeException;

/**
 * In-memory MailReaderInterface used by tests (bound over
 * MailClientFactory, see MailControllerTest) instead of hitting a real
 * mail server. Seeded per test with plain arrays via
 * seedFolders()/seedMessages() — mutable mid-test on purpose, so a
 * test can prove page 2+ never caches beyond MailSetting::
 * cache_ttl_seconds while page 1 only picks up a change via an
 * explicit refresh=1 (see MailMessageHeaderCache's own docblock).
 */
class FakeMailClient implements MailReaderInterface
{
    /** @var array<int, list<MailFolderDTO>> */
    private array $folders = [];

    /** @var array<int, array<string, list<MailMessageDTO>>> */
    private array $messages = [];

    /**
     * @param  list<MailFolderDTO>  $folders
     */
    public function seedFolders(MailAccount $account, array $folders): void
    {
        $this->folders[$account->id] = $folders;
    }

    /**
     * @param  list<MailMessageDTO>  $messages
     */
    public function seedMessages(MailAccount $account, string $folder, array $messages): void
    {
        $this->messages[$account->id][$folder] = $messages;
    }

    public function listFolders(MailAccount $account): array
    {
        return $this->folders[$account->id] ?? [];
    }

    public function listMessages(MailAccount $account, string $folder, int $page, int $perPage): array
    {
        $all = $this->messages[$account->id][$folder] ?? [];
        $summaries = array_map(fn (MailMessageDTO $message) => new MailMessageSummaryDTO(
            uid: $message->uid,
            subject: $message->subject,
            fromAddress: $message->fromAddress,
            fromName: $message->fromName,
            date: $message->date,
            hasAttachments: $message->attachments !== [],
            isRead: true,
        ), $all);

        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_slice($summaries, $offset, $perPage),
            'total' => count($summaries),
        ];
    }

    public function fetchMessage(MailAccount $account, string $folder, string $uid): MailMessageDTO
    {
        $message = collect($this->messages[$account->id][$folder] ?? [])->first(fn (MailMessageDTO $m) => $m->uid === $uid);

        if ($message === null) {
            throw new RuntimeException("Message {$uid} not found in {$folder}.");
        }

        return $message;
    }

    public function fetchAttachment(MailAccount $account, string $folder, string $uid, string $attachmentId): MailAttachmentDTO
    {
        $message = $this->fetchMessage($account, $folder, $uid);
        $attachment = collect($message->attachments)->first(fn ($a) => $a->id === $attachmentId);

        if ($attachment === null) {
            throw new RuntimeException("Attachment {$attachmentId} not found on message {$uid}.");
        }

        return new MailAttachmentDTO(
            filename: $attachment->filename,
            mimeType: $attachment->mimeType,
            sizeBytes: $attachment->sizeBytes ?? 0,
            contents: 'fake-attachment-bytes',
        );
    }

    public function testConnection(MailAccount $account): array
    {
        return ['ok' => true, 'message' => 'OK (fake)'];
    }
}
