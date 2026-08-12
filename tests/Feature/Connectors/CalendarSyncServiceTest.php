<?php

use Fazzinipierluigi\CrmCore\Models\CalendarEventExternalLink;
use Fazzinipierluigi\CrmCore\Models\Connector;
use Fazzinipierluigi\CrmCore\Models\ConnectorUserMailbox;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use App\Models\User;
use Fazzinipierluigi\CrmCore\Services\Connectors\CalendarSyncService;
use Database\Seeders\CalendarEntitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function syncCalendarEntity(): Entity
{
    test()->seed(CalendarEntitySeeder::class);

    return Entity::where('slug', 'calendario')->firstOrFail();
}

function fakeGraphAuth(): void
{
    Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'tok-123', 'expires_in' => 3600])]);
}

/**
 * @return array{0: Connector, 1: ConnectorUserMailbox, 2: User}
 */
function connectorWithGraphMailbox(string $direction = 'bidirectional'): array
{
    $connector = Connector::create([
        'type' => 'exchange_graph',
        'name' => 'Outlook 365',
        'slug' => 'outlook-365-'.uniqid(),
        'sync_direction' => $direction,
        'sync_interval_minutes' => 15,
        'config' => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's'],
    ]);
    $user = User::factory()->create();
    $mailbox = ConnectorUserMailbox::create(['connector_id' => $connector->id, 'user_id' => $user->id, 'mailbox_email' => 'mario@example.com']);

    return [$connector, $mailbox, $user];
}

test('importing a new external event creates a local record and a link', function () {
    fakeGraphAuth();
    Http::fake([
        'graph.microsoft.com/v1.0/users/mario@example.com/calendarView/delta*' => Http::response([
            'value' => [[
                'id' => 'graph-evt-1', 'changeKey' => 'ck-1', 'subject' => 'Riunione',
                'body' => ['content' => 'Nota'],
                'start' => ['dateTime' => '2026-08-10T10:00:00'], 'end' => ['dateTime' => '2026-08-10T11:00:00'],
                'showAs' => 'busy', 'isCancelled' => false,
            ]],
            '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/delta?$deltatoken=next',
        ]),
    ]);
    $entity = syncCalendarEntity();
    [$connector, $mailbox, $user] = connectorWithGraphMailbox('import_only');

    app(CalendarSyncService::class)->syncConnector($connector);

    $record = EntityRecord::forEntity($entity)->newQuery()->where('title', 'Riunione')->first();
    expect($record)->not->toBeNull();
    expect((int) $record->user_id)->toBe($user->id);
    expect(CalendarEventExternalLink::where('external_id', 'graph-evt-1')->exists())->toBeTrue();
    expect($connector->fresh()->last_sync_status)->toBe('success');
});

test('a newer external change updates the local record', function () {
    fakeGraphAuth();
    $entity = syncCalendarEntity();
    [$connector, $mailbox, $user] = connectorWithGraphMailbox('import_only');

    $record = EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $user->id, 'title' => 'Vecchio titolo', 'show_as' => 'busy', 'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00', 'end_datetime' => '2026-08-10 11:00:00',
    ]);
    CalendarEventExternalLink::create([
        'entity_record_id' => $record->id, 'connector_id' => $connector->id, 'user_id' => $user->id,
        'external_id' => 'graph-evt-1', 'sync_hash' => 'stale', 'last_synced_at' => now()->subHour(),
    ]);

    Http::fake([
        'graph.microsoft.com/v1.0/users/mario@example.com/calendarView/delta*' => Http::response([
            'value' => [[
                'id' => 'graph-evt-1', 'changeKey' => 'ck-2', 'subject' => 'Titolo aggiornato',
                'start' => ['dateTime' => '2026-08-10T10:00:00'], 'end' => ['dateTime' => '2026-08-10T11:00:00'],
                'showAs' => 'busy', 'isCancelled' => false,
                'lastModifiedDateTime' => now()->addMinute()->toIso8601String(),
            ]],
        ]),
    ]);

    app(CalendarSyncService::class)->syncConnector($connector);

    expect($record->fresh()->title)->toBe('Titolo aggiornato');
});

test('a stale external change does not overwrite a newer local edit', function () {
    fakeGraphAuth();
    $entity = syncCalendarEntity();
    [$connector, $mailbox, $user] = connectorWithGraphMailbox('import_only');

    $record = EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $user->id, 'title' => 'Modificato di recente', 'show_as' => 'busy', 'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00', 'end_datetime' => '2026-08-10 11:00:00',
    ]);
    CalendarEventExternalLink::create([
        'entity_record_id' => $record->id, 'connector_id' => $connector->id, 'user_id' => $user->id,
        'external_id' => 'graph-evt-1', 'sync_hash' => 'stale', 'last_synced_at' => now()->subDay(),
    ]);

    Http::fake([
        'graph.microsoft.com/v1.0/users/mario@example.com/calendarView/delta*' => Http::response([
            'value' => [[
                'id' => 'graph-evt-1', 'changeKey' => 'ck-2', 'subject' => 'Titolo esterno vecchio',
                'start' => ['dateTime' => '2026-08-10T10:00:00'], 'end' => ['dateTime' => '2026-08-10T11:00:00'],
                'showAs' => 'busy', 'isCancelled' => false,
                'lastModifiedDateTime' => now()->subHour()->toIso8601String(),
            ]],
        ]),
    ]);

    app(CalendarSyncService::class)->syncConnector($connector);

    expect($record->fresh()->title)->toBe('Modificato di recente');
});

test('exporting a new local record creates it remotely and stores a link', function () {
    fakeGraphAuth();
    Http::fake(['graph.microsoft.com/v1.0/users/mario@example.com/events' => Http::response(['id' => 'new-evt', 'changeKey' => 'ck-new'], 201)]);
    $entity = syncCalendarEntity();
    [$connector, $mailbox, $user] = connectorWithGraphMailbox('export_only');

    $record = EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $user->id, 'title' => 'Locale', 'show_as' => 'busy', 'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00', 'end_datetime' => '2026-08-10 11:00:00',
    ]);

    app(CalendarSyncService::class)->syncConnector($connector);

    $link = CalendarEventExternalLink::where('entity_record_id', $record->id)->first();
    expect($link)->not->toBeNull();
    expect($link->external_id)->toBe('new-evt');
});

test('exporting an unchanged local record does not push again', function () {
    fakeGraphAuth();
    $entity = syncCalendarEntity();
    [$connector, $mailbox, $user] = connectorWithGraphMailbox('export_only');

    $record = EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $user->id, 'title' => 'Locale', 'show_as' => 'busy', 'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00', 'end_datetime' => '2026-08-10 11:00:00',
    ]);

    $service = app(CalendarSyncService::class);
    Http::fake(['graph.microsoft.com/v1.0/users/mario@example.com/events' => Http::response(['id' => 'new-evt', 'changeKey' => 'ck-new'], 201)]);
    $service->syncConnector($connector);

    Http::fake(); // any further request now fails the test via assertNothingSent below
    $service->syncConnector($connector);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/events'));
});

test('a local deletion is propagated to the remote side', function () {
    fakeGraphAuth();
    $entity = syncCalendarEntity();
    [$connector, $mailbox, $user] = connectorWithGraphMailbox('export_only');

    CalendarEventExternalLink::create([
        'entity_record_id' => 999999, 'connector_id' => $connector->id, 'user_id' => $user->id,
        'external_id' => 'gone-evt', 'sync_hash' => 'x', 'last_synced_at' => now(),
    ]);

    Http::fake(['graph.microsoft.com/v1.0/users/mario@example.com/events/gone-evt' => Http::response(null, 204)]);

    app(CalendarSyncService::class)->syncConnector($connector);

    expect(CalendarEventExternalLink::where('external_id', 'gone-evt')->exists())->toBeFalse();
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'gone-evt'));
});

test('one failing mailbox does not stop the others and is reported', function () {
    $connector = Connector::create([
        'type' => 'exchange_graph', 'name' => 'Outlook 365', 'slug' => 'outlook-365-multi',
        'sync_direction' => 'import_only', 'sync_interval_minutes' => 15,
        'config' => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's'],
    ]);
    $goodUser = User::factory()->create();
    $badUser = User::factory()->create();
    ConnectorUserMailbox::create(['connector_id' => $connector->id, 'user_id' => $badUser->id, 'mailbox_email' => 'bad@example.com']);
    ConnectorUserMailbox::create(['connector_id' => $connector->id, 'user_id' => $goodUser->id, 'mailbox_email' => 'good@example.com']);
    syncCalendarEntity();

    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok-123', 'expires_in' => 3600]),
        'graph.microsoft.com/v1.0/users/bad@example.com/calendarView/delta*' => Http::response(['error' => 'boom'], 500),
        'graph.microsoft.com/v1.0/users/good@example.com/calendarView/delta*' => Http::response(['value' => []]),
    ]);

    app(CalendarSyncService::class)->syncConnector($connector);

    $fresh = $connector->fresh();
    expect($fresh->last_sync_status)->toBe('partial_failure');
    expect($fresh->last_sync_message)->toContain('bad@example.com');
});
