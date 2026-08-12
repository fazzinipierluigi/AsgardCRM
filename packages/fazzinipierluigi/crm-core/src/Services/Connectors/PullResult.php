<?php

namespace Fazzinipierluigi\CrmCore\Services\Connectors;

use Illuminate\Support\Collection;

/**
 * The outcome of a Connector::pull() call: the events fetched, plus an
 * opaque cursor to persist in ConnectorSyncState for the next
 * incremental pull — null for connectors with no such concept (a full
 * range pull every time, see EwsExchangeConnector).
 */
final readonly class PullResult
{
    /**
     * @param  Collection<int, ExternalCalendarEvent>  $events
     */
    public function __construct(
        public Collection $events,
        public ?string $nextSyncToken,
    ) {}
}
