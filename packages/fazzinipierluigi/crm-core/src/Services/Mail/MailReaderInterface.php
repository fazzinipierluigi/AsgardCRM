<?php

namespace Fazzinipierluigi\CrmCore\Services\Mail;

use Fazzinipierluigi\CrmCore\Models\MailAccount;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailAttachmentDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailFolderDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailMessageDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailMessageSummaryDTO;

/**
 * Reads a mailbox live, on demand — no implementation of this
 * interface may cache/persist message content itself; the one
 * exception (a folder's first page of listMessages() headers) is
 * cached one layer up, by MailController/MailMessageHeaderCache, never
 * inside a reader. Concrete implementations: ImapMailReader,
 * Pop3MailReader, EwsMailClient, GraphMailClient — resolved
 * per-account by MailClientFactory::readerFor().
 */
interface MailReaderInterface
{
    /**
     * @return list<MailFolderDTO>
     */
    public function listFolders(MailAccount $account): array;

    /**
     * @return array{items: list<MailMessageSummaryDTO>, total: int}
     */
    public function listMessages(MailAccount $account, string $folder, int $page, int $perPage): array;

    public function fetchMessage(MailAccount $account, string $folder, string $uid): MailMessageDTO;

    public function fetchAttachment(MailAccount $account, string $folder, string $uid, string $attachmentId): MailAttachmentDTO;

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(MailAccount $account): array;
}
