<?php

namespace Fazzinipierluigi\CrmCore\Services\Mail\Exchange;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Raw SOAP client for Exchange Web Services mail operations — a
 * separate, mail-shaped sibling of Fazzinipierluigi\CrmCore\Services\Connectors\Exchange\
 * EwsSoapClient (calendar), built the same way (hand-written XML over
 * Http, Basic auth or best-effort NTLM via raw curl options, no SDK
 * dependency) but with FindFolder/FindItem/GetItem/GetAttachment
 * instead of calendar CRUD. Kept separate rather than extending the
 * calendar client so the two systems can evolve independently.
 *
 * $config is either a MailAccount's own direct EWS credentials
 * (ews_url/username/password/use_ntlm, no impersonation — one mailbox,
 * one set of credentials) or, from M5 on, a shared MailConnector's
 * service-account credentials with ExchangeImpersonation (one account,
 * many mailboxes) — impersonateSmtp is only sent when given.
 */
class EwsMailSoapClient
{
    private const SOAP_NS = 'http://schemas.xmlsoap.org/soap/envelope/';

    private const TYPES_NS = 'http://schemas.microsoft.com/exchange/services/2006/types';

    private const MESSAGES_NS = 'http://schemas.microsoft.com/exchange/services/2006/messages';

    /**
     * @param  array<string, mixed>  $config  ews_url/username/password/use_ntlm
     */
    public function __construct(private readonly array $config) {}

    /**
     * Traversal="Deep" returns every folder under the mailbox root in
     * one call, each carrying its own ParentFolderId — cheaper than
     * the old Shallow call plus a per-folder follow-up, and it's what
     * lets the webmail UI show subfolders at all (Shallow only ever
     * saw the top-level folders, so a folder nested under e.g. Inbox
     * was invisible).
     *
     * @return list<array{id: string, name: string, hasChildren: bool, parentId: ?string}>
     */
    public function findFolders(?string $impersonateSmtp = null): array
    {
        $body = <<<XML
            <m:FindFolder xmlns:m="{$this->ns('m')}" xmlns:t="{$this->ns('t')}" Traversal="Deep">
                <m:FolderShape>
                    <t:BaseShape>Default</t:BaseShape>
                </m:FolderShape>
                <m:ParentFolderIds>
                    <t:DistinguishedFolderId Id="msgfolderroot" />
                </m:ParentFolderIds>
            </m:FindFolder>
            XML;

        $response = $this->send($body, $impersonateSmtp);
        $folders = $response->xpath('//t:Folder') ?: [];

        return array_map(function (SimpleXMLElement $folder) {
            $folder->registerXPathNamespace('t', self::TYPES_NS);
            $folderId = $folder->xpath('t:FolderId')[0] ?? null;
            $parentFolderId = $folder->xpath('t:ParentFolderId')[0] ?? null;
            $childFolderCount = (string) ($folder->xpath('t:ChildFolderCount')[0] ?? '0');

            return [
                'id' => $folderId !== null ? (string) $folderId->attributes()['Id'] : '',
                'name' => (string) ($folder->xpath('t:DisplayName')[0] ?? ''),
                'hasChildren' => $childFolderCount !== '0',
                'parentId' => $parentFolderId !== null ? (string) $parentFolderId->attributes()['Id'] : null,
            ];
        }, $folders);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function findMessages(string $folderId, int $offset, int $maxEntries, ?string $impersonateSmtp = null): array
    {
        $body = <<<XML
            <m:FindItem xmlns:m="{$this->ns('m')}" xmlns:t="{$this->ns('t')}" Traversal="Shallow">
                <m:ItemShape>
                    <t:BaseShape>Default</t:BaseShape>
                </m:ItemShape>
                <m:IndexedPageItemView MaxEntriesReturned="{$maxEntries}" Offset="{$offset}" BasePoint="Beginning" />
                <m:ParentFolderIds>
                    <t:FolderId Id="{$this->escape($folderId)}" />
                </m:ParentFolderIds>
            </m:FindItem>
            XML;

        $response = $this->send($body, $impersonateSmtp);
        $rootFolder = $response->xpath('//m:RootFolder')[0] ?? null;
        $totalItemsInView = $rootFolder !== null ? (int) $rootFolder->attributes()['TotalItemsInView'] : 0;
        $items = $response->xpath('//t:Message') ?: [];

        return [
            'items' => array_map($this->parseMessageSummary(...), $items),
            'total' => $totalItemsInView,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getItem(string $itemId, ?string $impersonateSmtp = null): array
    {
        $body = <<<XML
            <m:GetItem xmlns:m="{$this->ns('m')}" xmlns:t="{$this->ns('t')}">
                <m:ItemShape>
                    <t:BaseShape>AllProperties</t:BaseShape>
                    <t:BodyType>HTML</t:BodyType>
                </m:ItemShape>
                <m:ItemIds>
                    <t:ItemId Id="{$this->escape($itemId)}" />
                </m:ItemIds>
            </m:GetItem>
            XML;

        $response = $this->send($body, $impersonateSmtp);
        $message = $response->xpath('//t:Message')[0] ?? null;

        if ($message === null) {
            throw new RuntimeException('Risposta EWS senza il messaggio richiesto.');
        }

        return $this->parseFullMessage($message);
    }

    /**
     * @return array{filename: string, mimeType: ?string, content: string}
     */
    public function getAttachment(string $attachmentId, ?string $impersonateSmtp = null): array
    {
        $body = <<<XML
            <m:GetAttachment xmlns:m="{$this->ns('m')}" xmlns:t="{$this->ns('t')}">
                <m:AttachmentShape>
                    <t:IncludeMimeContent>true</t:IncludeMimeContent>
                </m:AttachmentShape>
                <m:AttachmentIds>
                    <t:AttachmentId Id="{$this->escape($attachmentId)}" />
                </m:AttachmentIds>
            </m:GetAttachment>
            XML;

        $response = $this->send($body, $impersonateSmtp);
        $attachment = $response->xpath('//t:FileAttachment')[0] ?? null;

        if ($attachment === null) {
            throw new RuntimeException('Risposta EWS senza allegato.');
        }

        $attachment->registerXPathNamespace('t', self::TYPES_NS);

        return [
            'filename' => (string) ($attachment->xpath('t:Name')[0] ?? 'allegato'),
            'mimeType' => (string) ($attachment->xpath('t:ContentType')[0] ?? '') ?: null,
            'content' => (string) base64_decode((string) ($attachment->xpath('t:Content')[0] ?? ''), true),
        ];
    }

    public function ping(?string $impersonateSmtp = null): void
    {
        $this->findFolders($impersonateSmtp);
    }

    /**
     * CreateItem with MessageDisposition="SendAndSaveCopy" — sends the
     * message and drops a copy in Sent Items in one call, no separate
     * "save then send" round-trip.
     *
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     * @param  list<array{filename: string, mimeType: ?string, contents: string}>  $attachments
     */
    public function sendMessage(string $subject, string $bodyHtml, array $to, array $cc, array $bcc, array $attachments, ?string $inReplyTo, ?string $impersonateSmtp = null): void
    {
        $recipientsXml = function (array $addresses, string $tag) {
            if ($addresses === []) {
                return '';
            }

            $mailboxes = implode('', array_map(
                fn (string $address) => "<t:Mailbox><t:EmailAddress>{$this->escape($address)}</t:EmailAddress></t:Mailbox>",
                $addresses
            ));

            return "<t:{$tag}>{$mailboxes}</t:{$tag}>";
        };

        $attachmentsXml = $attachments === [] ? '' : '<t:Attachments>'.implode('', array_map(
            fn (array $attachment) => '<t:FileAttachment>'
                ."<t:Name>{$this->escape($attachment['filename'])}</t:Name>"
                .($attachment['mimeType'] !== null ? "<t:ContentType>{$this->escape($attachment['mimeType'])}</t:ContentType>" : '')
                .'<t:Content>'.base64_encode($attachment['contents']).'</t:Content>'
                .'</t:FileAttachment>',
            $attachments
        )).'</t:Attachments>';

        $inReplyToXml = $inReplyTo !== null ? "<t:InReplyTo>{$this->escape($inReplyTo)}</t:InReplyTo>" : '';

        $itemXml = <<<XML
            <t:Message>
                <t:Subject>{$this->escape($subject)}</t:Subject>
                <t:Body BodyType="HTML">{$this->escape($bodyHtml)}</t:Body>
                {$attachmentsXml}
                {$inReplyToXml}
                {$recipientsXml($to, 'ToRecipients')}
                {$recipientsXml($cc, 'CcRecipients')}
                {$recipientsXml($bcc, 'BccRecipients')}
            </t:Message>
            XML;

        $requestBody = <<<XML
            <m:CreateItem xmlns:m="{$this->ns('m')}" xmlns:t="{$this->ns('t')}" MessageDisposition="SendAndSaveCopy">
                <m:Items>{$itemXml}</m:Items>
            </m:CreateItem>
            XML;

        $this->send($requestBody, $impersonateSmtp);
    }

    private function send(string $bodyXml, ?string $impersonateSmtp): SimpleXMLElement
    {
        $envelope = $this->buildEnvelope($bodyXml, $impersonateSmtp);
        $response = $this->httpClient()->withBody($envelope, 'text/xml; charset=utf-8')
            ->withHeaders(['SOAPAction' => ''])
            ->post((string) ($this->config['ews_url'] ?? ''));

        if ($response->failed()) {
            throw new RuntimeException("Errore HTTP EWS ({$response->status()}): {$response->body()}");
        }

        $xml = new SimpleXMLElement($response->body());
        $xml->registerXPathNamespace('soap', self::SOAP_NS);
        $xml->registerXPathNamespace('m', self::MESSAGES_NS);
        $xml->registerXPathNamespace('t', self::TYPES_NS);

        $attributes = ($xml->xpath('//m:ResponseMessages/*[1]')[0] ?? null)?->attributes();

        if ($attributes !== null && (string) $attributes['ResponseClass'] === 'Error') {
            $message = (string) ($xml->xpath('//m:MessageText')[0] ?? 'Errore EWS sconosciuto.');

            throw new RuntimeException("Errore EWS: {$message}");
        }

        return $xml;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseMessageSummary(SimpleXMLElement $item): array
    {
        $item->registerXPathNamespace('t', self::TYPES_NS);
        $itemId = $item->xpath('t:ItemId')[0] ?? null;
        $from = $item->xpath('t:From/t:Mailbox')[0] ?? null;
        $from?->registerXPathNamespace('t', self::TYPES_NS);

        return [
            'id' => $itemId !== null ? (string) $itemId->attributes()['Id'] : '',
            'subject' => (string) ($item->xpath('t:Subject')[0] ?? ''),
            'fromAddress' => $from !== null ? (string) ($from->xpath('t:EmailAddress')[0] ?? '') : null,
            'fromName' => $from !== null ? (string) ($from->xpath('t:Name')[0] ?? '') : null,
            'dateTimeSent' => (string) ($item->xpath('t:DateTimeSent')[0] ?? ''),
            'hasAttachments' => (string) ($item->xpath('t:HasAttachments')[0] ?? 'false') === 'true',
            'isRead' => (string) ($item->xpath('t:IsRead')[0] ?? 'true') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFullMessage(SimpleXMLElement $message): array
    {
        $message->registerXPathNamespace('t', self::TYPES_NS);
        $itemId = $message->xpath('t:ItemId')[0] ?? null;
        $from = $message->xpath('t:From/t:Mailbox')[0] ?? null;
        $from?->registerXPathNamespace('t', self::TYPES_NS);

        $toAddresses = array_map(function (SimpleXMLElement $mailbox) {
            $mailbox->registerXPathNamespace('t', self::TYPES_NS);

            return (string) ($mailbox->xpath('t:EmailAddress')[0] ?? '');
        }, $message->xpath('t:ToRecipients/t:Mailbox') ?: []);

        $attachments = array_map(function (SimpleXMLElement $attachment) {
            $attachment->registerXPathNamespace('t', self::TYPES_NS);
            $attachmentId = $attachment->xpath('t:AttachmentId')[0] ?? null;

            return [
                'id' => $attachmentId !== null ? (string) $attachmentId->attributes()['Id'] : '',
                'filename' => (string) ($attachment->xpath('t:Name')[0] ?? 'allegato'),
                'mimeType' => (string) ($attachment->xpath('t:ContentType')[0] ?? '') ?: null,
                'sizeBytes' => ($size = (string) ($attachment->xpath('t:Size')[0] ?? '')) !== '' ? (int) $size : null,
            ];
        }, $message->xpath('t:Attachments/t:FileAttachment') ?: []);

        return [
            'id' => $itemId !== null ? (string) $itemId->attributes()['Id'] : '',
            'subject' => (string) ($message->xpath('t:Subject')[0] ?? ''),
            'fromAddress' => $from !== null ? (string) ($from->xpath('t:EmailAddress')[0] ?? '') : null,
            'fromName' => $from !== null ? (string) ($from->xpath('t:Name')[0] ?? '') : null,
            'toAddresses' => array_values(array_filter($toAddresses)),
            'dateTimeSent' => (string) ($message->xpath('t:DateTimeSent')[0] ?? ''),
            'messageId' => (string) ($message->xpath('t:InternetMessageId')[0] ?? '') ?: null,
            'bodyHtml' => (string) ($message->xpath('t:Body')[0] ?? ''),
            'attachments' => $attachments,
        ];
    }

    private function buildEnvelope(string $bodyXml, ?string $impersonateSmtp): string
    {
        $header = $impersonateSmtp !== null
            ? <<<XML
                <t:RequestServerVersion Version="Exchange2013" />
                <t:ExchangeImpersonation>
                    <t:ConnectingSID>
                        <t:PrimarySmtpAddress>{$this->escape($impersonateSmtp)}</t:PrimarySmtpAddress>
                    </t:ConnectingSID>
                </t:ExchangeImpersonation>
                XML
            : '<t:RequestServerVersion Version="Exchange2013" />';

        return <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:soap="{$this->ns('soap')}" xmlns:t="{$this->ns('t')}">
                <soap:Header>
                    {$header}
                </soap:Header>
                <soap:Body>
                    {$bodyXml}
                </soap:Body>
            </soap:Envelope>
            XML;
    }

    private function httpClient(): PendingRequest
    {
        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');

        if ($this->config['use_ntlm'] ?? false) {
            return Http::withOptions([
                'curl' => [
                    CURLOPT_HTTPAUTH => CURLAUTH_NTLM,
                    CURLOPT_USERPWD => "{$username}:{$password}",
                ],
            ]);
        }

        return Http::withBasicAuth($username, $password);
    }

    private function ns(string $prefix): string
    {
        return match ($prefix) {
            'soap' => self::SOAP_NS,
            't' => self::TYPES_NS,
            'm' => self::MESSAGES_NS,
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
