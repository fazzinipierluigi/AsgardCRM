<?php

namespace App\Services\Connectors;

/**
 * The outcome of a Connector::push() call — the external identifiers to
 * persist on CalendarEventExternalLink so future syncs can find this
 * event again.
 */
final readonly class PushResult
{
    public function __construct(
        public string $externalId,
        public ?string $externalChangeKey,
    ) {}
}
