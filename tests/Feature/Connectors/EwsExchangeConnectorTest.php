<?php

use App\Models\Connector;
use App\Models\ConnectorUserMailbox;
use App\Models\User;
use App\Services\Connectors\Exchange\EwsExchangeConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function ewsConnectorWithMailbox(): array
{
    $connector = Connector::create([
        'type' => 'exchange_ews',
        'name' => 'Exchange On-Prem',
        'slug' => 'exchange-on-prem',
        'sync_direction' => 'bidirectional',
        'sync_interval_minutes' => 15,
        'config' => ['ews_url' => 'https://mail.example.com/EWS/Exchange.asmx', 'username' => 'svc-calendar@example.com', 'password' => 'secret'],
    ]);
    $user = User::factory()->create();
    $mailbox = ConnectorUserMailbox::create(['connector_id' => $connector->id, 'user_id' => $user->id, 'mailbox_email' => 'mario@example.com']);

    return [$connector, $mailbox];
}

test('testConnection reports success on a valid response', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('find-item-response'), 200, ['Content-Type' => 'text/xml'])]);
    [$connector] = ewsConnectorWithMailbox();

    $result = app(EwsExchangeConnector::class)->testConnection($connector);

    expect($result['ok'])->toBeTrue();
});

test('testConnection reports failure on an EWS error', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('error-access-denied'), 200, ['Content-Type' => 'text/xml'])]);
    [$connector] = ewsConnectorWithMailbox();

    $result = app(EwsExchangeConnector::class)->testConnection($connector);

    expect($result['ok'])->toBeFalse();
});

test('pull maps EWS calendar items and never returns a sync token', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('find-item-response'), 200, ['Content-Type' => 'text/xml'])]);
    [$connector, $mailbox] = ewsConnectorWithMailbox();

    $result = app(EwsExchangeConnector::class)->pull($connector, $mailbox, null);

    expect($result->events)->toHaveCount(1);
    $event = $result->events->first();
    expect($event->externalId)->toBe('AAMk-item-1');
    expect($event->showAs)->toBe('available');
    expect($result->nextSyncToken)->toBeNull();
});

test('push creates a new event when no external id is given', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('create-item-response'), 200, ['Content-Type' => 'text/xml'])]);
    [$connector, $mailbox] = ewsConnectorWithMailbox();

    $result = app(EwsExchangeConnector::class)->push($connector, $mailbox, [
        'title' => 'Nuovo evento',
        'description' => 'Desc',
        'start' => now(),
        'end' => now()->addHour(),
        'show_as' => 'busy',
        'status' => 'confirmed',
    ], null, null);

    expect($result->externalId)->toBe('AAMk-new-item');
});

test('push updates an existing event when an external id is given', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('update-item-response'), 200, ['Content-Type' => 'text/xml'])]);
    [$connector, $mailbox] = ewsConnectorWithMailbox();

    $result = app(EwsExchangeConnector::class)->push($connector, $mailbox, [
        'title' => 'Aggiornato',
        'description' => null,
        'start' => now(),
        'end' => now()->addHour(),
        'show_as' => 'out_of_office',
        'status' => 'cancelled',
    ], 'AAMk-item-1', 'CK-1');

    expect($result->externalId)->toBe('AAMk-updated-item');
});

test('delete returns true on success and on already-gone', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('delete-item-response-not-found'), 200, ['Content-Type' => 'text/xml'])]);
    [$connector, $mailbox] = ewsConnectorWithMailbox();

    expect(app(EwsExchangeConnector::class)->delete($connector, $mailbox, 'gone-item'))->toBeTrue();
});
