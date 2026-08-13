<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Mail\DTO;

use Carbon\CarbonImmutable;

/**
 * A single message fetched live in full (see
 * MailReaderInterface::fetchMessage()) — body included, but never
 * persisted: MailController::attach() only ever writes the handful of
 * header fields onto the entity_email bookmark row, not $htmlBody/
 * $textBody/$attachments.
 */
final readonly class MailMessageDTO
{
    /**
     * @param  list<string>  $toAddresses
     * @param  list<MailAttachmentSummaryDTO>  $attachments
     */
    public function __construct(
        public string $uid,
        public string $subject,
        public ?string $fromAddress,
        public ?string $fromName,
        public array $toAddresses,
        public ?CarbonImmutable $date,
        public ?string $messageId,
        public ?string $textBody,
        public ?string $htmlBody,
        public array $attachments,
    ) {}
}
