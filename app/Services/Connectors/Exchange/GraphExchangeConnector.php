<?php

namespace App\Services\Connectors\Exchange;

use App\Models\Connector;
use App\Models\ConnectorSyncState;
use App\Models\ConnectorUserMailbox;
use App\Services\Connectors\ConnectorInterface;
use App\Services\Connectors\ExternalCalendarEvent;
use App\Services\Connectors\PullResult;
use App\Services\Connectors\PushResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Microsoft Graph connector for Exchange Online/Office 365/Outlook.com
 * mailboxes. Every request forces `Prefer: outlook.timezone="UTC"` so
 * Graph always returns/accepts plain UTC datetimes — sidesteps having
 * to map Graph's Windows timezone names (e.g. "Pacific Standard Time")
 * to IANA ones just to parse a pulled event.
 *
 * Uses `/calendarView/delta` for incremental pulls (see GraphTokenClient
 * for auth): the very first pull for a mailbox has no delta link yet, so
 * it bootstraps with a bounded range (dictated by DEFAULT_RANGE_PAST/
 * FUTURE) rather than a user's entire calendar history.
 */
class GraphExchangeConnector implements ConnectorInterface
{
    private const BASE_URL = 'https://graph.microsoft.com/v1.0';

    private const DEFAULT_RANGE_PAST_MONTHS = 1;

    private const DEFAULT_RANGE_FUTURE_MONTHS = 6;

    /**
     * Hard ceiling on @odata.nextLink pages followed in one pull() call —
     * guards against looping forever if a server bug (or a broken fake in
     * a test) ever returns a self-referential nextLink.
     */
    private const MAX_PAGES = 500;

    public function __construct(private readonly GraphTokenClient $tokenClient) {}

    public function testConnection(Connector $connector): array
    {
        try {
            $token = $this->tokenClient->tokenFor($connector);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $response = Http::withToken($token)->get(self::BASE_URL.'/users', ['$top' => 1]);

        return $response->successful()
            ? ['ok' => true, 'message' => 'Connessione riuscita.']
            : ['ok' => false, 'message' => "Connessione fallita ({$response->status()}): {$response->body()}"];
    }

    public function pull(Connector $connector, ConnectorUserMailbox $mailbox, ?ConnectorSyncState $state): PullResult
    {
        $client = $this->client($connector);
        $mailboxEmail = $mailbox->mailbox_email;

        $url = $state?->delta_link ?? $this->initialDeltaUrl($mailboxEmail);
        $events = collect();
        $nextSyncToken = $state?->delta_link;
        $pages = 0;

        do {
            if (++$pages > self::MAX_PAGES) {
                throw new RuntimeException('Troppe pagine restituite da Graph durante il pull: interrotto per sicurezza.');
            }

            $response = $client->get($url);

            if ($response->failed()) {
                throw new RuntimeException("Errore Graph durante il pull ({$response->status()}): {$response->body()}");
            }

            $body = $response->json();

            foreach ($body['value'] ?? [] as $item) {
                // A delta response can include "@removed" stubs for items
                // deleted since the last sync — they carry no start/end/
                // subject, only an id, so they can't be mapped to an
                // ExternalCalendarEvent. Propagating remote deletions back
                // to local records isn't implemented in this MVP (see
                // CalendarSyncService) — skip rather than crash on them.
                if (isset($item['@removed'])) {
                    continue;
                }

                $events->push($this->toExternalEvent($item));
            }

            $url = $body['@odata.nextLink'] ?? null;
            $nextSyncToken = $body['@odata.deltaLink'] ?? $nextSyncToken;
        } while ($url !== null);

        return new PullResult($events, $nextSyncToken);
    }

    public function push(Connector $connector, ConnectorUserMailbox $mailbox, array $eventData, ?string $externalId, ?string $externalChangeKey): PushResult
    {
        $client = $this->client($connector);
        $mailboxEmail = $mailbox->mailbox_email;
        $payload = $this->toGraphPayload($eventData);

        $response = $externalId === null
            ? $client->post(self::BASE_URL."/users/{$mailboxEmail}/events", $payload)
            : $client->patch(self::BASE_URL."/users/{$mailboxEmail}/events/{$externalId}", $payload);

        if ($response->failed()) {
            throw new RuntimeException("Errore Graph durante il push ({$response->status()}): {$response->body()}");
        }

        $body = $response->json();

        return new PushResult($body['id'], $body['changeKey'] ?? null);
    }

    public function delete(Connector $connector, ConnectorUserMailbox $mailbox, string $externalId): bool
    {
        $client = $this->client($connector);
        $response = $client->delete(self::BASE_URL."/users/{$mailbox->mailbox_email}/events/{$externalId}");

        return $response->successful() || $response->status() === 404;
    }

    private function client(Connector $connector): PendingRequest
    {
        return Http::withToken($this->tokenClient->tokenFor($connector))
            ->withHeaders(['Prefer' => 'outlook.timezone="UTC"']);
    }

    private function initialDeltaUrl(string $mailboxEmail): string
    {
        $start = now()->subMonths(self::DEFAULT_RANGE_PAST_MONTHS)->toIso8601String();
        $end = now()->addMonths(self::DEFAULT_RANGE_FUTURE_MONTHS)->toIso8601String();

        return self::BASE_URL."/users/{$mailboxEmail}/calendarView/delta?startDateTime={$start}&endDateTime={$end}";
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function toExternalEvent(array $item): ExternalCalendarEvent
    {
        return new ExternalCalendarEvent(
            externalId: $item['id'],
            changeKey: $item['changeKey'] ?? null,
            subject: $item['subject'] ?? '',
            body: $item['body']['content'] ?? null,
            start: Carbon::parse($item['start']['dateTime'] ?? null),
            end: Carbon::parse($item['end']['dateTime'] ?? null),
            showAs: $this->mapShowAsFromGraph($item['showAs'] ?? null),
            isCancelled: (bool) ($item['isCancelled'] ?? false),
            lastModified: isset($item['lastModifiedDateTime']) ? Carbon::parse($item['lastModifiedDateTime']) : null,
        );
    }

    /**
     * @param  array{title: string, description: ?string, start: Carbon, end: Carbon, show_as: string, status: string}  $eventData
     * @return array<string, mixed>
     */
    private function toGraphPayload(array $eventData): array
    {
        return [
            'subject' => $eventData['title'],
            'body' => ['contentType' => 'text', 'content' => $eventData['description'] ?? ''],
            'start' => ['dateTime' => $eventData['start']->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $eventData['end']->format('Y-m-d\TH:i:s'), 'timeZone' => 'UTC'],
            'showAs' => $this->mapShowAsToGraph($eventData['show_as']),
            'isCancelled' => $eventData['status'] === 'cancelled',
        ];
    }

    /**
     * Graph's free/busy/tentative/oof/workingElsewhere collapses onto our
     * three values with some fidelity loss — tentative and
     * workingElsewhere both land on "busy", the closest safe default.
     */
    private function mapShowAsFromGraph(?string $value): string
    {
        return match ($value) {
            'free' => 'available',
            'oof' => 'out_of_office',
            default => 'busy',
        };
    }

    private function mapShowAsToGraph(string $value): string
    {
        return match ($value) {
            'available' => 'free',
            'out_of_office' => 'oof',
            default => 'busy',
        };
    }
}
