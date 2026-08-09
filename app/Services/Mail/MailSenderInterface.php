<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use App\Services\Mail\DTO\MailComposeDTO;

/**
 * Sends mail from a MailAccount — a separate interface from
 * MailReaderInterface because the transport isn't always the same:
 * IMAP/POP3/EWS-direct accounts send via SMTP regardless of how they
 * read, while a shared-connector Exchange account sends via
 * Graph/EWS, never SMTP (no personal SMTP credential exists in that
 * flow). See MailClientFactory::senderFor().
 */
interface MailSenderInterface
{
    public function send(MailAccount $account, MailComposeDTO $message): void;

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(MailAccount $account): array;
}
