<?php

namespace Fazzinipierluigi\CrmCore\Services\Mail\DTO;

/**
 * A single attachment's bytes, fetched live on demand (see
 * MailReaderInterface::fetchAttachment()) — streamed straight through
 * MailController::attachmentDownload() to the browser, never written
 * to a Laravel filesystem disk.
 */
final readonly class MailAttachmentDTO
{
    public function __construct(
        public string $filename,
        public ?string $mimeType,
        public int $sizeBytes,
        public string $contents,
    ) {}
}
