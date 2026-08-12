<?php

namespace App\Services\Connectors;

use App\Enums\ConnectorSyncDirection;
use App\Models\CalendarEventExternalLink;
use App\Models\Connector;
use App\Models\ConnectorSyncState;
use App\Models\ConnectorUserMailbox;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestrates one Connector's sync: for each mapped mailbox
 * (ConnectorUserMailbox), imports external events into entity_calendario
 * and/or exports local ones out, matching them up via
 * CalendarEventExternalLink. A connector implementation (see
 * ConnectorInterface) only ever speaks the external API — this service
 * owns all the "which local record does this external event correspond
 * to" bookkeeping.
 *
 * Conflict resolution is deliberately simple last-write-wins (external
 * lastModified vs local updated_at) — not a field-level merge. One
 * mailbox failing doesn't abort the others; each is wrapped in its own
 * try/catch so a single bad mailbox/token doesn't block the rest of the
 * connector's users.
 */
class CalendarSyncService
{
    public function __construct(private readonly ConnectorFactory $factory) {}

    public function syncConnector(Connector $connector): void
    {
        $entity = Entity::where('slug', 'calendario')->where('is_installed', true)->first();

        if ($entity === null) {
            $connector->update(['last_sync_status' => 'failed', 'last_sync_message' => 'Il Calendario non è installato.']);

            return;
        }

        $client = $this->factory->make($connector);
        $errors = [];

        foreach ($connector->mailboxes as $mailbox) {
            try {
                $this->syncMailbox($connector, $client, $entity, $mailbox);
            } catch (Throwable $e) {
                Log::warning("Connector #{$connector->id} sync failed for mailbox {$mailbox->mailbox_email}: {$e->getMessage()}");
                $errors[] = "{$mailbox->mailbox_email}: {$e->getMessage()}";
            }
        }

        $connector->update([
            'last_synced_at' => now(),
            'last_sync_status' => $errors === [] ? 'success' : 'partial_failure',
            'last_sync_message' => $errors === [] ? null : implode(' | ', $errors),
        ]);
    }

    private function syncMailbox(Connector $connector, ConnectorInterface $client, Entity $entity, ConnectorUserMailbox $mailbox): void
    {
        $direction = $connector->sync_direction;

        if ($direction !== ConnectorSyncDirection::ExportOnly) {
            $this->importFromRemote($connector, $client, $entity, $mailbox);
        }

        if ($direction !== ConnectorSyncDirection::ImportOnly) {
            $this->exportToRemote($connector, $client, $entity, $mailbox);
        }
    }

    private function importFromRemote(Connector $connector, ConnectorInterface $client, Entity $entity, ConnectorUserMailbox $mailbox): void
    {
        $state = ConnectorSyncState::where('connector_id', $connector->id)
            ->where('connector_user_mailbox_id', $mailbox->id)
            ->first();

        $result = $client->pull($connector, $mailbox, $state);

        foreach ($result->events as $event) {
            $this->applyExternalEvent($connector, $entity, $mailbox, $event);
        }

        if ($result->nextSyncToken !== null) {
            ConnectorSyncState::updateOrCreate(
                ['connector_id' => $connector->id, 'connector_user_mailbox_id' => $mailbox->id],
                ['delta_link' => $result->nextSyncToken, 'last_synced_at' => now()]
            );
        }
    }

    private function applyExternalEvent(Connector $connector, Entity $entity, ConnectorUserMailbox $mailbox, ExternalCalendarEvent $event): void
    {
        $link = CalendarEventExternalLink::where('connector_id', $connector->id)
            ->where('external_id', $event->externalId)
            ->first();

        $eventData = [
            'title' => $event->subject,
            'description' => $event->body,
            'start' => $event->start,
            'end' => $event->end,
            'show_as' => $event->showAs,
            'status' => $event->isCancelled ? 'cancelled' : 'confirmed',
        ];
        $hash = $this->hashFor($eventData);
        $attributes = [
            'title' => $eventData['title'],
            'description' => $eventData['description'],
            'show_as' => $eventData['show_as'],
            'status' => $eventData['status'],
            'start_datetime' => $event->start->format('Y-m-d H:i:s'),
            'end_datetime' => $event->end->format('Y-m-d H:i:s'),
        ];

        $query = EntityRecord::forEntity($entity)->newQuery();

        if ($link === null) {
            $record = $query->create($attributes + ['user_id' => $mailbox->user_id]);

            CalendarEventExternalLink::create([
                'entity_record_id' => $record->id,
                'connector_id' => $connector->id,
                'user_id' => $mailbox->user_id,
                'external_id' => $event->externalId,
                'external_change_key' => $event->changeKey,
                'sync_hash' => $hash,
                'last_synced_at' => now(),
            ]);

            return;
        }

        $record = $query->find($link->entity_record_id);

        if ($record === null) {
            // Locally deleted since the last sync — exportToRemote() is
            // responsible for propagating that deletion outward and
            // removing the link; importing would just resurrect it.
            return;
        }

        // Last-write-wins: an external change only overwrites the local
        // record if it's actually newer than the local record's own last
        // change, so a local edit made after the external one isn't
        // clobbered by a stale pull.
        if ($event->lastModified !== null && $event->lastModified->lessThanOrEqualTo($record->updated_at)) {
            return;
        }

        $record->update($attributes);

        $link->update([
            'external_change_key' => $event->changeKey,
            'sync_hash' => $hash,
            'last_synced_at' => now(),
        ]);
    }

    private function exportToRemote(Connector $connector, ConnectorInterface $client, Entity $entity, ConnectorUserMailbox $mailbox): void
    {
        $this->propagateLocalDeletions($connector, $client, $mailbox);

        $records = EntityRecord::forEntity($entity)->newQuery()->where('user_id', $mailbox->user_id)->get();

        foreach ($records as $record) {
            $eventData = $this->eventDataFor($record);
            $hash = $this->hashFor($eventData);

            $link = CalendarEventExternalLink::where('connector_id', $connector->id)
                ->where('entity_record_id', $record->id)
                ->first();

            if ($link !== null && $link->sync_hash === $hash) {
                continue;
            }

            $result = $client->push($connector, $mailbox, $eventData, $link?->external_id, $link?->external_change_key);

            if ($link === null) {
                CalendarEventExternalLink::create([
                    'entity_record_id' => $record->id,
                    'connector_id' => $connector->id,
                    'user_id' => $mailbox->user_id,
                    'external_id' => $result->externalId,
                    'external_change_key' => $result->externalChangeKey,
                    'sync_hash' => $hash,
                    'last_synced_at' => now(),
                ]);
            } else {
                $link->update([
                    'external_change_key' => $result->externalChangeKey,
                    'sync_hash' => $hash,
                    'last_synced_at' => now(),
                ]);
            }
        }
    }

    private function propagateLocalDeletions(Connector $connector, ConnectorInterface $client, ConnectorUserMailbox $mailbox): void
    {
        $links = CalendarEventExternalLink::where('connector_id', $connector->id)
            ->where('user_id', $mailbox->user_id)
            ->get();

        if ($links->isEmpty()) {
            return;
        }

        $entity = Entity::where('slug', 'calendario')->firstOrFail();
        $existingIds = EntityRecord::forEntity($entity)->newQuery()
            ->whereIn('id', $links->pluck('entity_record_id'))
            ->pluck('id')
            ->all();

        foreach ($links as $link) {
            if (in_array($link->entity_record_id, $existingIds, true)) {
                continue;
            }

            $client->delete($connector, $mailbox, $link->external_id);
            $link->delete();
        }
    }

    /**
     * @return array{title: string, description: ?string, start: Carbon, end: Carbon, show_as: string, status: string}
     */
    private function eventDataFor(EntityRecord $record): array
    {
        return [
            'title' => (string) $record->title,
            'description' => $record->description,
            'start' => Carbon::parse($record->start_datetime),
            'end' => Carbon::parse($record->end_datetime),
            'show_as' => (string) $record->show_as,
            'status' => (string) $record->status,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function hashFor(array $attributes): string
    {
        $normalized = $attributes;

        foreach (['start', 'end'] as $key) {
            if (isset($normalized[$key]) && $normalized[$key] instanceof Carbon) {
                $normalized[$key] = $normalized[$key]->toIso8601String();
            }
        }

        ksort($normalized);

        return hash('sha256', json_encode($normalized));
    }
}
