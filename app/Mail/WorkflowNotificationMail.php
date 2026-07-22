<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent by the "Invia email" workflow action — subject and body are
 * plain strings already rendered from the action's `{{ variabile }}`
 * template by WorkflowExpressionEvaluator::renderTemplate().
 */
class WorkflowNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $renderedSubject, public string $renderedBody) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renderedSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->renderedBody,
        );
    }
}
