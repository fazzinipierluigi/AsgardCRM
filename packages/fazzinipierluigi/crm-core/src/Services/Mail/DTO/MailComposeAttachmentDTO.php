<?php

namespace Fazzinipierluigi\CrmCore\Services\Mail\DTO;

/**
 * A file the user attaches while composing/replying/forwarding —
 * bytes held only for the single send() call, never persisted.
 */
final readonly class MailComposeAttachmentDTO
{
    public function __construct(
        public string $filename,
        public ?string $mimeType,
        public string $contents,
    ) {}
}
