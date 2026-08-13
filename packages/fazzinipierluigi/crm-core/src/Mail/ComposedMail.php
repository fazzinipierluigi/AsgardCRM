<?php

namespace Fazzinipierluigi\AsgardCRM\Mail;

use Fazzinipierluigi\AsgardCRM\Models\MailAccount;
use Fazzinipierluigi\AsgardCRM\Services\Mail\DTO\MailComposeDTO;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * A user-composed message (new/reply/forward, see MailController::send())
 * — built from a MailComposeDTO, sent through SmtpMailSender's
 * per-account ephemeral mailer. Deliberately NOT ShouldQueue: mail is
 * always sent synchronously here, same as every other MailReaderInterface/
 * MailSenderInterface call in this module (no background queue/job is
 * part of the "E-mail" module's design — everything happens inline,
 * on demand).
 */
class ComposedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly MailAccount $account,
        private readonly MailComposeDTO $compose,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->account->email_address, $this->account->name),
            to: $this->compose->to,
            cc: $this->compose->cc,
            bcc: $this->compose->bcc,
            subject: $this->compose->subject,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->compose->bodyHtml);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn ($attachment) => Attachment::fromData(fn () => $attachment->contents, $attachment->filename)
                ->withMime($attachment->mimeType ?? 'application/octet-stream'),
            $this->compose->attachments,
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            references: $this->compose->references !== null ? [$this->compose->references] : [],
            text: $this->compose->inReplyTo !== null ? ['In-Reply-To' => $this->compose->inReplyTo] : [],
        );
    }
}
