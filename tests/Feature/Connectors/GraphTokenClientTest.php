<?php

use Fazzinipierluigi\CrmCore\Models\Connector;
use Fazzinipierluigi\CrmCore\Services\Connectors\Exchange\GraphTokenClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function graphConnector(): Connector
{
    return Connector::create([
        'type' => 'exchange_graph',
        'name' => 'Outlook 365',
        'slug' => 'outlook-365',
        'sync_direction' => 'bidirectional',
        'sync_interval_minutes' => 15,
        'config' => ['tenant_id' => 'tenant-1', 'client_id' => 'client-1', 'client_secret' => 'secret-1'],
    ]);
}

test('fetches and returns an access token', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok-123', 'expires_in' => 3600]),
    ]);

    $token = app(GraphTokenClient::class)->tokenFor(graphConnector());

    expect($token)->toBe('tok-123');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'login.microsoftonline.com/tenant-1')
        && $request['grant_type'] === 'client_credentials'
        && $request['client_id'] === 'client-1'
        && $request['client_secret'] === 'secret-1');
});

test('caches the token and does not re-request it within its TTL', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok-123', 'expires_in' => 3600]),
    ]);
    $connector = graphConnector();
    $client = app(GraphTokenClient::class);

    $client->tokenFor($connector);
    $client->tokenFor($connector);

    Http::assertSentCount(1);
});

test('throws when the token endpoint rejects the credentials', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 401),
    ]);

    expect(fn () => app(GraphTokenClient::class)->tokenFor(graphConnector()))->toThrow(RuntimeException::class);
});
