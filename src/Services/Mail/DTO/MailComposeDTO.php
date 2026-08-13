<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Mail\DTO;

/**
 * A message to send — built by MailController::send()/reply()/forward()
 * from the compose form, handed to MailSenderInterface::send().
 * inReplyTo/references carry the RFC Message-ID headers a reply/forward
 * threads against; both null for a brand-new message.
 */
final readonly class MailComposeDTO
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     * @param  list<MailComposeAttachmentDTO>  $attachments
     */
    public function __construct(
        public array $to,
        public array $cc,
        public array $bcc,
        public string $subject,
        public string $bodyHtml,
        public array $attachments,
        public ?string $inReplyTo = null,
        public ?string $references = null,
    ) {}
}
