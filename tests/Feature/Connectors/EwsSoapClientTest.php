<?php

use App\Services\Connectors\Exchange\EwsSoapClient;
use Illuminate\Support\Facades\Http;

function ewsFixture(string $name): string
{
    return file_get_contents(base_path("tests/Fixtures/ews/{$name}.xml"));
}

function ewsConfig(array $overrides = []): array
{
    return array_merge([
        'ews_url' => 'https://mail.example.com/EWS/Exchange.asmx',
        'username' => 'svc-calendar@example.com',
        'password' => 'secret',
        'use_ntlm' => false,
    ], $overrides);
}

test('findCalendarItems sends a CalendarView FindItem request and parses items', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('find-item-response'), 200, ['Content-Type' => 'text/xml'])]);

    $client = new EwsSoapClient(ewsConfig());
    $items = $client->findCalendarItems('mario@example.com', '2026-08-01T00:00:00Z', '2026-08-31T00:00:00Z');

    expect($items)->toHaveCount(1);
    expect($items[0]['id'])->toBe('AAMk-item-1');
    expect($items[0]['changeKey'])->toBe('CK-1');
    expect($items[0]['subject'])->toBe('Riunione EWS');
    expect($items[0]['legacyFreeBusyStatus'])->toBe('Free');

    Http::assertSent(function ($request) {
        return str_contains($request->body(), '<m:FindItem')
            && str_contains($request->body(), 'CalendarView StartDate="2026-08-01T00:00:00Z" EndDate="2026-08-31T00:00:00Z"')
            && str_contains($request->body(), '<t:PrimarySmtpAddress>mario@example.com</t:PrimarySmtpAddress>');
    });
});

test('createItem sends a CreateItem request and returns the new item id', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('create-item-response'), 200, ['Content-Type' => 'text/xml'])]);

    $client = new EwsSoapClient(ewsConfig());
    $result = $client->createItem('mario@example.com', 'Nuovo evento', 'Descrizione', '2026-08-10T10:00:00Z', '2026-08-10T11:00:00Z', 'Busy');

    expect($result)->toBe(['id' => 'AAMk-new-item', 'changeKey' => 'CK-new']);
    Http::assertSent(fn ($request) => str_contains($request->body(), 'SendMeetingInvitations="SendToNone"'));
});

test('createItem escapes special characters in the subject', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('create-item-response'), 200, ['Content-Type' => 'text/xml'])]);

    $client = new EwsSoapClient(ewsConfig());
    $client->createItem('mario@example.com', 'A & B <test>', '', '2026-08-10T10:00:00Z', '2026-08-10T11:00:00Z', 'Busy');

    Http::assertSent(fn ($request) => str_contains($request->body(), 'A &amp; B &lt;test&gt;'));
});

test('updateItem sends an UpdateItem request and returns the resulting item id', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('update-item-response'), 200, ['Content-Type' => 'text/xml'])]);

    $client = new EwsSoapClient(ewsConfig());
    $result = $client->updateItem('mario@example.com', 'AAMk-item-1', 'CK-1', 'Titolo aggiornato', 'Nota', '2026-08-10T10:00:00Z', '2026-08-10T11:00:00Z', 'OOF');

    expect($result)->toBe(['id' => 'AAMk-updated-item', 'changeKey' => 'CK-updated']);
    Http::assertSent(fn ($request) => str_contains($request->body(), 'Id="AAMk-item-1" ChangeKey="CK-1"'));
});

test('deleteItem returns true on success', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('delete-item-response-success'), 200, ['Content-Type' => 'text/xml'])]);

    $client = new EwsSoapClient(ewsConfig());

    expect($client->deleteItem('mario@example.com', 'AAMk-item-1', 'CK-1'))->toBeTrue();
});

test('deleteItem returns true when the item is already gone', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('delete-item-response-not-found'), 200, ['Content-Type' => 'text/xml'])]);

    $client = new EwsSoapClient(ewsConfig());

    expect($client->deleteItem('mario@example.com', 'AAMk-item-1', 'CK-1'))->toBeTrue();
});

test('a generic EWS error throws', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('error-access-denied'), 200, ['Content-Type' => 'text/xml'])]);

    $client = new EwsSoapClient(ewsConfig());

    expect(fn () => $client->findCalendarItems('mario@example.com', '2026-08-01T00:00:00Z', '2026-08-31T00:00:00Z'))
        ->toThrow(RuntimeException::class);
});

test('use_ntlm still completes the request via the curl-options auth path', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsFixture('find-item-response'), 200, ['Content-Type' => 'text/xml'])]);

    $client = new EwsSoapClient(ewsConfig(['use_ntlm' => true]));
    $items = $client->findCalendarItems('mario@example.com', '2026-08-01T00:00:00Z', '2026-08-31T00:00:00Z');

    expect($items)->toHaveCount(1);
});
