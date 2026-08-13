<?php

use Fazzinipierluigi\AsgardCRM\Models\Connector;
use Fazzinipierluigi\AsgardCRM\Models\ConnectorSyncState;
use Fazzinipierluigi\AsgardCRM\Models\ConnectorUserMailbox;
use Fazzinipierluigi\AsgardCRM\Services\Connectors\Exchange\GraphExchangeConnector;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeGraphToken(): void
{
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok-123', 'expires_in' => 3600]),
    ]);
}

function graphConnectorWithMailbox(): array
{
    $connector = Connector::create([
        'type' => 'exchange_graph',
        'name' => 'Outlook 365',
        'slug' => 'outlook-365',
        'sync_direction' => 'bidirectional',
        'sync_interval_minutes' => 15,
        'config' => ['tenant_id' => 'tenant-1', 'client_id' => 'client-1', 'client_secret' => 'secret-1'],
    ]);
    $user = User::factory()->create();
    $mailbox = ConnectorUserMailbox::create(['connector_id' => $connector->id, 'user_id' => $user->id, 'mailbox_email' => 'mario@example.com']);

    return [$connector, $mailbox];
}

test('testConnection reports success when Graph responds', function () {
    fakeGraphToken();
    Http::fake(['graph.microsoft.com/v1.0/users*' => Http::response(['value' => []])]);
    [$connector] = graphConnectorWithMailbox();

    $result = app(GraphExchangeConnector::class)->testConnection($connector);

    expect($result['ok'])->toBeTrue();
});

test('testConnection reports failure when the token cannot be obtained', function () {
    Http::fake(['login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 401)]);
    [$connector] = graphConnectorWithMailbox();

    $result = app(GraphExchangeConnector::class)->testConnection($connector);

    expect($result['ok'])->toBeFalse();
});

test('pull maps Graph events and returns the next delta link', function () {
    fakeGraphToken();
    Http::fake([
        'graph.microsoft.com/v1.0/users/mario@example.com/calendarView/delta*' => Http::response([
            'value' => [
                [
                    'id' => 'graph-evt-1',
                    'changeKey' => 'ck-1',
                    'subject' => 'Riunione',
                    'body' => ['content' => 'Nota'],
                    'start' => ['dateTime' => '2026-08-10T10:00:00', 'timeZone' => 'UTC'],
                    'end' => ['dateTime' => '2026-08-10T11:00:00', 'timeZone' => 'UTC'],
                    'showAs' => 'free',
                    'isCancelled' => false,
                    'lastModifiedDateTime' => '2026-08-01T09:00:00Z',
                ],
            ],
            '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/users/mario@example.com/calendarView/delta?$deltatoken=abc',
        ]),
    ]);
    [$connector, $mailbox] = graphConnectorWithMailbox();

    $result = app(GraphExchangeConnector::class)->pull($connector, $mailbox, null);

    expect($result->events)->toHaveCount(1);
    $event = $result->events->first();
    expect($event->externalId)->toBe('graph-evt-1');
    expect($event->subject)->toBe('Riunione');
    expect($event->showAs)->toBe('available');
    expect($result->nextSyncToken)->toContain('deltatoken=abc');
});

test('pull follows nextLink pagination', function () {
    fakeGraphToken();
    Http::fake([
        'graph.microsoft.com/v1.0/users/mario@example.com/calendarView/delta*' => Http::sequence()
            ->push([
                'value' => [['id' => 'evt-1', 'subject' => 'A', 'start' => ['dateTime' => '2026-08-10T10:00:00'], 'end' => ['dateTime' => '2026-08-10T11:00:00']]],
                '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/users/mario@example.com/calendarView/delta?$skip=1',
            ])
            ->push([
                'value' => [['id' => 'evt-2', 'subject' => 'B', 'start' => ['dateTime' => '2026-08-11T10:00:00'], 'end' => ['dateTime' => '2026-08-11T11:00:00']]],
                '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/users/mario@example.com/calendarView/delta?$deltatoken=final',
            ]),
    ]);
    [$connector, $mailbox] = graphConnectorWithMailbox();

    $result = app(GraphExchangeConnector::class)->pull($connector, $mailbox, null);

    expect($result->events)->toHaveCount(2);
    expect($result->events->pluck('externalId')->all())->toBe(['evt-1', 'evt-2']);
});

test('pull reuses a stored delta link', function () {
    fakeGraphToken();
    Http::fake([
        'graph.microsoft.com/v1.0/users/mario@example.com/calendarView/delta?$deltatoken=stored' => Http::response(['value' => []]),
    ]);
    [$connector, $mailbox] = graphConnectorWithMailbox();
    $state = ConnectorSyncState::create([
        'connector_id' => $connector->id,
        'connector_user_mailbox_id' => $mailbox->id,
        'delta_link' => 'https://graph.microsoft.com/v1.0/users/mario@example.com/calendarView/delta?$deltatoken=stored',
    ]);

    app(GraphExchangeConnector::class)->pull($connector, $mailbox, $state);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'deltatoken=stored'));
});

test('push creates a new event when no external id is given', function () {
    fakeGraphToken();
    Http::fake([
        'graph.microsoft.com/v1.0/users/mario@example.com/events' => Http::response(['id' => 'new-evt', 'changeKey' => 'ck-new'], 201),
    ]);
    [$connector, $mailbox] = graphConnectorWithMailbox();

    $result = app(GraphExchangeConnector::class)->push($connector, $mailbox, [
        'title' => 'Nuovo evento',
        'description' => 'Desc',
        'start' => now(),
        'end' => now()->addHour(),
        'show_as' => 'busy',
        'status' => 'confirmed',
    ], null, null);

    expect($result->externalId)->toBe('new-evt');
    expect($result->externalChangeKey)->toBe('ck-new');
    Http::assertSent(fn ($request) => $request->method() === 'POST');
});

test('push updates an existing event when an external id is given', function () {
    fakeGraphToken();
    Http::fake([
        'graph.microsoft.com/v1.0/users/mario@example.com/events/existing-evt' => Http::response(['id' => 'existing-evt', 'changeKey' => 'ck-updated']),
    ]);
    [$connector, $mailbox] = graphConnectorWithMailbox();

    $result = app(GraphExchangeConnector::class)->push($connector, $mailbox, [
        'title' => 'Aggiornato',
        'description' => null,
        'start' => now(),
        'end' => now()->addHour(),
        'show_as' => 'out_of_office',
        'status' => 'cancelled',
    ], 'existing-evt', 'ck-old');

    expect($result->externalId)->toBe('existing-evt');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH' && $request['isCancelled'] === true && $request['showAs'] === 'oof');
});

test('delete returns true on success and on already-gone (404)', function () {
    fakeGraphToken();
    Http::fake([
        'graph.microsoft.com/v1.0/users/mario@example.com/events/gone-evt' => Http::response(null, 404),
    ]);
    [$connector, $mailbox] = graphConnectorWithMailbox();

    expect(app(GraphExchangeConnector::class)->delete($connector, $mailbox, 'gone-evt'))->toBeTrue();
});
