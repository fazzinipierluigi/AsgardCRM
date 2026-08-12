<?php

namespace Fazzinipierluigi\CrmCore\Services\Mail\DTO;

use Carbon\CarbonImmutable;

/**
 * One row in a folder's message list — header data only, fetched live
 * from MailReaderInterface::listMessages(). Page 2+ is cached only for
 * MailSetting::cache_ttl_seconds (a short, ephemeral window); a
 * folder's first page is instead persisted by
 * Fazzinipierluigi\CrmCore\Services\Mail\MailMessageHeaderCache, so it can survive across
 * requests/users. `uid` is the protocol-native message identifier
 * within `folder` (IMAP UID, POP3 UIDL, EWS ItemId) — the pair is what
 * a MailController::attach() call later stores on the entity_email
 * bookmark row to re-fetch this exact message.
 */
final readonly class MailMessageSummaryDTO
{
    public function __construct(
        public string $uid,
        public string $subject,
        public ?string $fromAddress,
        public ?string $fromName,
        public ?CarbonImmutable $date,
        public bool $hasAttachments,
        public bool $isRead,
    ) {}
}
