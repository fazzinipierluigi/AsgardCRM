<?php

namespace Fazzinipierluigi\CrmCore\Services\Connectors;

use Illuminate\Support\Carbon;

/**
 * A calendar event as read from an external source, already normalized
 * to this app's vocabulary (show_as/status), ready for
 * CalendarSyncService to reconcile against a local calendario record.
 */
final readonly class ExternalCalendarEvent
{
    public function __construct(
        public string $externalId,
        public ?string $changeKey,
        public string $subject,
        public ?string $body,
        public Carbon $start,
        public Carbon $end,
        public string $showAs,
        public bool $isCancelled,
        public ?Carbon $lastModified,
    ) {}
}
