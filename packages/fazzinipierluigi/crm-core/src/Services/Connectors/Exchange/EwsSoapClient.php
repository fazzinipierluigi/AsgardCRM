<?php

namespace Fazzinipierluigi\CrmCore\Services\Connectors\Exchange;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Raw SOAP client for Exchange Web Services (on-premise/hybrid), used
 * by EwsExchangeConnector. No SDK dependency — same "build the XML by
 * hand over Http" philosophy as the app's own OAuth2 flow in
 * SocialLoginController, just for SOAP instead of REST/JSON.
 *
 * Auth: Basic first (works on modern on-prem/hybrid EWS with
 * impersonation), NTLM as an explicit best-effort fallback — Guzzle
 * (Laravel's Http client) has no native NTLM support, so it's done via
 * raw curl options (CURLOPT_HTTPAUTH/CURLOPT_USERPWD).
 *
 * ExchangeImpersonation lets one service account (with the
 * "ms-Exch-EPI-Impersonation" right granted) act on behalf of any
 * mailbox given its SMTP address — the prerequisite for one Connector
 * to sync many users' calendars, same idea as Graph's app-only + mailbox
 * mapping (see GraphExchangeConnector/ConnectorUserMailbox).
 */
class EwsSoapClient
{
    private const SOAP_NS = 'http://schemas.xmlsoap.org/soap/envelope/';

    private const TYPES_NS = 'http://schemas.microsoft.com/exchange/services/2006/types';

    private const MESSAGES_NS = 'http://schemas.microsoft.com/exchange/services/2006/messages';

    /**
     * @param  array<string, mixed>  $config  ews_url/username/password/use_ntlm, see ConnectorController::configFor()
     */
    public function __construct(private readonly array $config) {}

    /**
     * FindItem with a CalendarView, requesting full properties directly
     * (no separate GetItem round-trip per item).
     *
     * @return array<int, array<string, mixed>> raw parsed CalendarItem fields
     */
    public function findCalendarItems(string $mailboxEmail, string $startIso, string $endIso): array
    {
        $body = <<<XML
            <m:FindItem xmlns:m="{$this->ns('m')}" xmlns:t="{$this->ns('t')}" Traversal="Shallow">
                <m:ItemShape>
                    <t:BaseShape>AllProperties</t:BaseShape>
                </m:ItemShape>
                <m:CalendarView StartDate="{$this->escape($startIso)}" EndDate="{$this->escape($endIso)}" />
                <m:ParentFolderIds>
                    <t:DistinguishedFolderId Id="calendar">
                        <t:Mailbox><t:EmailAddress>{$this->escape($mailboxEmail)}</t:EmailAddress></t:Mailbox>
                    </t:DistinguishedFolderId>
                </m:ParentFolderIds>
            </m:FindItem>
            XML;

        $response = $this->send($body, $mailboxEmail);
        $items = $response->xpath('//t:CalendarItem') ?: [];

        return array_map($this->parseCalendarItem(...), $items);
    }

    /**
     * @return array{id: string, changeKey: ?string}
     */
    public function createItem(string $mailboxEmail, string $subject, string $body, string $startIso, string $endIso, string $legacyFreeBusyStatus): array
    {
        $itemXml = <<<XML
            <t:CalendarItem>
                <t:Subject>{$this->escape($subject)}</t:Subject>
                <t:Body BodyType="Text">{$this->escape($body)}</t:Body>
                <t:Start>{$this->escape($startIso)}</t:Start>
                <t:End>{$this->escape($endIso)}</t:End>
                <t:LegacyFreeBusyStatus>{$this->escape($legacyFreeBusyStatus)}</t:LegacyFreeBusyStatus>
            </t:CalendarItem>
            XML;

        $requestBody = <<<XML
            <m:CreateItem xmlns:m="{$this->ns('m')}" xmlns:t="{$this->ns('t')}" SendMeetingInvitations="SendToNone">
                <m:SavedItemFolderId>
                    <t:DistinguishedFolderId Id="calendar">
                        <t:Mailbox><t:EmailAddress>{$this->escape($mailboxEmail)}</t:EmailAddress></t:Mailbox>
                    </t:DistinguishedFolderId>
                </m:SavedItemFolderId>
                <m:Items>{$itemXml}</m:Items>
            </m:CreateItem>
            XML;

        $response = $this->send($requestBody, $mailboxEmail);

        return $this->extractItemId($response);
    }

    /**
     * @return array{id: string, changeKey: ?string}
     */
    public function updateItem(string $mailboxEmail, string $itemId, string $changeKey, string $subject, string $body, string $startIso, string $endIso, string $legacyFreeBusyStatus): array
    {
        $fields = [
            'Subject' => $subject,
            'Body' => $body,
            'Start' => $startIso,
            'End' => $endIso,
            'LegacyFreeBusyStatus' => $legacyFreeBusyStatus,
        ];

        $changes = '';
        foreach ($fields as $field => $value) {
            $fieldValueXml = $field === 'Body'
                ? "<t:Body BodyType=\"Text\">{$this->escape($value)}</t:Body>"
                : "<t:{$field}>{$this->escape($value)}</t:{$field}>";

            $changes .= <<<XML
                <t:SetItemField>
                    <t:FieldURI FieldURI="calendar:{$field}" />
                    <t:CalendarItem>{$fieldValueXml}</t:CalendarItem>
                </t:SetItemField>
                XML;
        }

        $requestBody = <<<XML
            <m:UpdateItem xmlns:m="{$this->ns('m')}" xmlns:t="{$this->ns('t')}" MessageDisposition="SaveOnly" SendMeetingInvitationsOrCancellations="SendToNone" ConflictResolution="AlwaysOverwrite">
                <m:ItemChanges>
                    <t:ItemChange>
                        <t:ItemId Id="{$this->escape($itemId)}" ChangeKey="{$this->escape($changeKey)}" />
                        <t:Updates>{$changes}</t:Updates>
                    </t:ItemChange>
                </m:ItemChanges>
            </m:UpdateItem>
            XML;

        $response = $this->send($requestBody, $mailboxEmail);

        return $this->extractItemId($response);
    }

    public function deleteItem(string $mailboxEmail, string $itemId, string $changeKey): bool
    {
        $requestBody = <<<XML
            <m:DeleteItem xmlns:m="{$this->ns('m')}" xmlns:t="{$this->ns('t')}" DeleteType="HardDelete" SendMeetingCancellations="SendToNone">
                <m:ItemIds>
                    <t:ItemId Id="{$this->escape($itemId)}" ChangeKey="{$this->escape($changeKey)}" />
                </m:ItemIds>
            </m:DeleteItem>
            XML;

        $response = $this->send($requestBody, $mailboxEmail, allowItemNotFound: true);

        if ($this->responseClass($response) !== 'Error') {
            return true;
        }

        // Already gone counts as a successful delete — see ConnectorInterface::delete().
        return (string) ($response->xpath('//m:ResponseCode')[0] ?? '') === 'ErrorItemNotFound';
    }

    /**
     * A minimal FindItem with Traversal only, used just to confirm the
     * credentials/endpoint work without touching any real calendar data.
     */
    public function ping(string $mailboxEmail): void
    {
        $this->findCalendarItems($mailboxEmail, now()->toIso8601String(), now()->addMinute()->toIso8601String());
    }

    private function send(string $bodyXml, string $impersonateSmtp, bool $allowItemNotFound = false): SimpleXMLElement
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

        $responseClass = $this->responseClass($xml);

        if ($responseClass === 'Error' && ! $allowItemNotFound) {
            $message = (string) ($xml->xpath('//m:MessageText')[0] ?? 'Errore EWS sconosciuto.');

            throw new RuntimeException("Errore EWS: {$message}");
        }

        return $xml;
    }

    private function responseClass(SimpleXMLElement $xml): ?string
    {
        $attributes = $xml->xpath('//m:ResponseMessages/*[1]')[0]->attributes() ?? null;

        return $attributes !== null ? (string) $attributes['ResponseClass'] : null;
    }

    /**
     * @return array{id: string, changeKey: ?string}
     */
    private function extractItemId(SimpleXMLElement $response): array
    {
        $itemId = $response->xpath('//t:ItemId')[0] ?? null;

        if ($itemId === null) {
            throw new RuntimeException('Risposta EWS senza ItemId.');
        }

        $attributes = $itemId->attributes();

        return [
            'id' => (string) $attributes['Id'],
            'changeKey' => isset($attributes['ChangeKey']) ? (string) $attributes['ChangeKey'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseCalendarItem(SimpleXMLElement $item): array
    {
        $item->registerXPathNamespace('t', self::TYPES_NS);
        $itemId = $item->xpath('t:ItemId')[0] ?? null;
        $attributes = $itemId?->attributes();

        return [
            'id' => $attributes !== null ? (string) $attributes['Id'] : '',
            'changeKey' => $attributes !== null && isset($attributes['ChangeKey']) ? (string) $attributes['ChangeKey'] : null,
            'subject' => (string) ($item->xpath('t:Subject')[0] ?? ''),
            'body' => (string) ($item->xpath('t:Body')[0] ?? ''),
            'start' => (string) ($item->xpath('t:Start')[0] ?? ''),
            'end' => (string) ($item->xpath('t:End')[0] ?? ''),
            'legacyFreeBusyStatus' => (string) ($item->xpath('t:LegacyFreeBusyStatus')[0] ?? ''),
            'isCancelled' => (string) ($item->xpath('t:IsCancelled')[0] ?? 'false') === 'true',
            'lastModifiedTime' => (string) ($item->xpath('t:LastModifiedTime')[0] ?? ''),
        ];
    }

    private function buildEnvelope(string $bodyXml, string $impersonateSmtp): string
    {
        return <<<XML
            <?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:soap="{$this->ns('soap')}" xmlns:t="{$this->ns('t')}">
                <soap:Header>
                    <t:RequestServerVersion Version="Exchange2013" />
                    <t:ExchangeImpersonation>
                        <t:ConnectingSID>
                            <t:PrimarySmtpAddress>{$this->escape($impersonateSmtp)}</t:PrimarySmtpAddress>
                        </t:ConnectingSID>
                    </t:ExchangeImpersonation>
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
