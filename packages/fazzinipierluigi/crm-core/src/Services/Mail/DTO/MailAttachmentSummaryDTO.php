<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Mail\DTO;

/**
 * One attachment's metadata as listed alongside a fetched message
 * (MailMessageDTO::$attachments) — not its bytes, see
 * MailAttachmentDTO for the actual download.
 */
final readonly class MailAttachmentSummaryDTO
{
    public function __construct(
        public string $id,
        public string $filename,
        public ?string $mimeType,
        public ?int $sizeBytes,
    ) {}
}
