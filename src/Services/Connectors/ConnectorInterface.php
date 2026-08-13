<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Connectors;

use Fazzinipierluigi\AsgardCRM\Models\Connector;
use Fazzinipierluigi\AsgardCRM\Models\ConnectorSyncState;
use Fazzinipierluigi\AsgardCRM\Models\ConnectorUserMailbox;
use Illuminate\Support\Carbon;

/**
 * Contract every external calendar source connector implements (see
 * Exchange\GraphExchangeConnector, Exchange\EwsExchangeConnector).
 * Consumed by CalendarSyncService, which owns matching pulled/pushed
 * events against local calendario records via CalendarEventExternalLink
 * — a connector only speaks the external API and this app's own
 * show_as/status vocabulary, it never touches EntityRecord itself.
 */
interface ConnectorInterface
{
    /**
     * Verify the connector's stored credentials actually work, without
     * touching any calendar data. Never throws — failures are reported
     * in the returned array so the admin UI can show them inline.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(Connector $connector): array;

    /**
     * Fetch what changed in the given mailbox since $state's cursor (or
     * everything in the connector's default range, if $state is null /
     * the connector has no incremental-sync concept).
     */
    public function pull(Connector $connector, ConnectorUserMailbox $mailbox, ?ConnectorSyncState $state): PullResult;

    /**
     * Create ($externalId === null) or update an event in the external
     * mailbox from local event data.
     *
     * @param  array{title: string, description: ?string, start: Carbon, end: Carbon, show_as: string, status: string}  $eventData
     */
    public function push(
        Connector $connector,
        ConnectorUserMailbox $mailbox,
        array $eventData,
        ?string $externalId,
        ?string $externalChangeKey,
    ): PushResult;

    /**
     * Delete an event in the external mailbox. Returns true if it's
     * gone afterward, including when it was already gone (404).
     */
    public function delete(Connector $connector, ConnectorUserMailbox $mailbox, string $externalId): bool;
}
