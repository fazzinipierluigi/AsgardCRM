<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Mail;

use Fazzinipierluigi\AsgardCRM\Enums\MailAccountProtocol;
use Fazzinipierluigi\AsgardCRM\Enums\MailConnectorType;
use Fazzinipierluigi\AsgardCRM\Models\MailAccount;
use Fazzinipierluigi\AsgardCRM\Services\Mail\Exchange\EwsMailClient;
use Fazzinipierluigi\AsgardCRM\Services\Mail\Exchange\GraphMailClient;
use Fazzinipierluigi\AsgardCRM\Services\Mail\Exchange\MailGraphTokenClient;

/**
 * Resolves the right MailReaderInterface/MailSenderInterface for a
 * given account — protocol plus whether it uses a shared
 * MailConnector, and that connector's own type, decide the concrete
 * class. Structurally the same idea as
 * Fazzinipierluigi\AsgardCRM\Services\Connectors\ConnectorFactory, but a separate class: mail
 * and calendar sync are unrelated concerns that happen to share this
 * "type picks an implementation" shape.
 */
class MailClientFactory
{
    public function readerFor(MailAccount $account): MailReaderInterface
    {
        $exchangeClient = $this->exchangeClient($account);

        if ($exchangeClient !== null) {
            return $exchangeClient;
        }

        return match ($account->protocol) {
            MailAccountProtocol::Imap => new ImapMailReader,
            MailAccountProtocol::Pop3 => new Pop3MailReader,
            MailAccountProtocol::Exchange => new EwsMailClient,
        };
    }

    /**
     * IMAP/POP3/EWS-direct accounts all send through the same personal
     * SMTP credentials in their own config, regardless of how they
     * read — a shared-connector Exchange account sends through that
     * same connector client instead (Graph/EWS), never SMTP.
     */
    public function senderFor(MailAccount $account): MailSenderInterface
    {
        $exchangeClient = $this->exchangeClient($account);

        if ($exchangeClient !== null) {
            return $exchangeClient;
        }

        return match ($account->protocol) {
            MailAccountProtocol::Imap, MailAccountProtocol::Pop3 => new SmtpMailSender,
            MailAccountProtocol::Exchange => new EwsMailClient,
        };
    }

    private function exchangeClient(MailAccount $account): EwsMailClient|GraphMailClient|null
    {
        if ($account->protocol !== MailAccountProtocol::Exchange || ! $account->usesSharedConnector()) {
            return null;
        }

        return $account->mailConnector->type === MailConnectorType::ExchangeGraph
            ? new GraphMailClient(new MailGraphTokenClient)
            : new EwsMailClient;
    }
}
