<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Connectors\Exchange;

use Fazzinipierluigi\AsgardCRM\Models\Connector;
use Fazzinipierluigi\AsgardCRM\Models\ConnectorSyncState;
use Fazzinipierluigi\AsgardCRM\Models\ConnectorUserMailbox;
use Fazzinipierluigi\AsgardCRM\Services\Connectors\ConnectorInterface;
use Fazzinipierluigi\AsgardCRM\Services\Connectors\ExternalCalendarEvent;
use Fazzinipierluigi\AsgardCRM\Services\Connectors\PullResult;
use Fazzinipierluigi\AsgardCRM\Services\Connectors\PushResult;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * EWS (on-premise/hybrid Exchange) connector, built on the raw SOAP
 * client in EwsSoapClient. Unlike Graph (see GraphExchangeConnector),
 * classic EWS has no simple, universally-supported incremental sync
 * protocol equivalent to a delta link (real support would mean
 * SyncFolderItems + persisted sync state, or a push subscription server
 * — both meaningfully more infrastructure) — so this MVP always does a
 * full range pull (DEFAULT_RANGE_PAST/FUTURE_MONTHS) and never writes a
 * ConnectorSyncState. That asymmetry with Graph is a known, deliberate
 * limitation, not a bug to paper over.
 */
class EwsExchangeConnector implements ConnectorInterface
{
    private const DEFAULT_RANGE_PAST_MONTHS = 1;

    private const DEFAULT_RANGE_FUTURE_MONTHS = 6;

    public function testConnection(Connector $connector): array
    {
        $mailboxEmail = (string) ($connector->config['username'] ?? '');

        if ($mailboxEmail === '') {
            return ['ok' => false, 'message' => 'Configura uno username (mailbox del servizio) prima di testare la connessione.'];
        }

        try {
            $this->soapClient($connector)->ping($mailboxEmail);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Connessione riuscita.'];
    }

    public function pull(Connector $connector, ConnectorUserMailbox $mailbox, ?ConnectorSyncState $state): PullResult
    {
        $client = $this->soapClient($connector);
        $start = now()->subMonths(self::DEFAULT_RANGE_PAST_MONTHS)->toIso8601String();
        $end = now()->addMonths(self::DEFAULT_RANGE_FUTURE_MONTHS)->toIso8601String();

        $items = $client->findCalendarItems($mailbox->mailbox_email, $start, $end);
        $events = collect($items)->map($this->toExternalEvent(...));

        // No delta link concept for EWS in this MVP — see class docblock.
        return new PullResult($events, null);
    }

    public function push(Connector $connector, ConnectorUserMailbox $mailbox, array $eventData, ?string $externalId, ?string $externalChangeKey): PushResult
    {
        $client = $this->soapClient($connector);
        $subject = $eventData['title'];
        $body = $eventData['description'] ?? '';
        $start = $this->toEwsDateTime($eventData['start']);
        $end = $this->toEwsDateTime($eventData['end']);
        $freeBusy = $this->mapShowAsToEws($eventData['show_as']);

        $result = $externalId === null
            ? $client->createItem($mailbox->mailbox_email, $subject, $body, $start, $end, $freeBusy)
            : $client->updateItem($mailbox->mailbox_email, $externalId, (string) $externalChangeKey, $subject, $body, $start, $end, $freeBusy);

        return new PushResult($result['id'], $result['changeKey']);
    }

    public function delete(Connector $connector, ConnectorUserMailbox $mailbox, string $externalId): bool
    {
        // EWS requires the current ChangeKey to delete an item, which
        // this interface doesn't carry — CalendarSyncService is expected
        // to pass the last known one via a push()-style call site; for a
        // pure delete-only path, refetch via a targeted CalendarView pull
        // isn't attempted here — pass an empty ChangeKey, which Exchange
        // accepts as "delete regardless of concurrent changes" on most
        // on-prem configurations.
        return $this->soapClient($connector)->deleteItem($mailbox->mailbox_email, $externalId, '');
    }

    private function soapClient(Connector $connector): EwsSoapClient
    {
        return new EwsSoapClient($connector->config ?? []);
    }

    private function toEwsDateTime(Carbon $date): string
    {
        return $date->clone()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function toExternalEvent(array $item): ExternalCalendarEvent
    {
        return new ExternalCalendarEvent(
            externalId: $item['id'],
            changeKey: $item['changeKey'],
            subject: $item['subject'] ?? '',
            body: $item['body'] !== '' ? $item['body'] : null,
            start: Carbon::parse($item['start']),
            end: Carbon::parse($item['end']),
            showAs: $this->mapShowAsFromEws($item['legacyFreeBusyStatus'] ?? null),
            isCancelled: (bool) ($item['isCancelled'] ?? false),
            lastModified: ! empty($item['lastModifiedTime']) ? Carbon::parse($item['lastModifiedTime']) : null,
        );
    }

    /**
     * EWS's Free/Tentative/Busy/OOF/WorkingElsewhere/NoData collapses
     * onto our three values the same way Graph's does (see
     * GraphExchangeConnector) — Tentative/WorkingElsewhere/NoData land
     * on "busy", the closest safe default.
     */
    private function mapShowAsFromEws(?string $value): string
    {
        return match ($value) {
            'Free' => 'available',
            'OOF' => 'out_of_office',
            default => 'busy',
        };
    }

    private function mapShowAsToEws(string $value): string
    {
        return match ($value) {
            'available' => 'Free',
            'out_of_office' => 'OOF',
            default => 'Busy',
        };
    }
}
