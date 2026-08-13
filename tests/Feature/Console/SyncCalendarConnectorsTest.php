<?php

use Fazzinipierluigi\AsgardCRM\Jobs\SyncConnectorJob;
use Fazzinipierluigi\AsgardCRM\Models\Connector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

function connectorFactory(array $overrides = []): Connector
{
    return Connector::create(array_merge([
        'type' => 'exchange_graph',
        'name' => 'Outlook 365',
        'slug' => 'outlook-365-'.uniqid(),
        'is_active' => true,
        'sync_direction' => 'bidirectional',
        'sync_interval_minutes' => 15,
    ], $overrides));
}

test('dispatches a job for a connector never synced before', function () {
    Bus::fake();
    $connector = connectorFactory(['last_synced_at' => null]);

    $this->artisan('calendar:sync-connectors')->assertSuccessful();

    Bus::assertDispatched(SyncConnectorJob::class, fn ($job) => $job->connector->is($connector));
});

test('does not dispatch a job for a connector not yet due', function () {
    Bus::fake();
    connectorFactory(['last_synced_at' => now(), 'sync_interval_minutes' => 60]);

    $this->artisan('calendar:sync-connectors')->assertSuccessful();

    Bus::assertNotDispatched(SyncConnectorJob::class);
});

test('dispatches a job for a connector whose interval has elapsed', function () {
    Bus::fake();
    $connector = connectorFactory(['last_synced_at' => now()->subMinutes(30), 'sync_interval_minutes' => 15]);

    $this->artisan('calendar:sync-connectors')->assertSuccessful();

    Bus::assertDispatched(SyncConnectorJob::class, fn ($job) => $job->connector->is($connector));
});

test('does not dispatch a job for an inactive connector', function () {
    Bus::fake();
    connectorFactory(['is_active' => false, 'last_synced_at' => null]);

    $this->artisan('calendar:sync-connectors')->assertSuccessful();

    Bus::assertNotDispatched(SyncConnectorJob::class);
});

test('--connector forces a single connector regardless of due-ness', function () {
    Bus::fake();
    $due = connectorFactory(['last_synced_at' => now(), 'sync_interval_minutes' => 60]);
    connectorFactory(['last_synced_at' => null]);

    $this->artisan('calendar:sync-connectors', ['--connector' => $due->slug])->assertSuccessful();

    Bus::assertDispatchedTimes(SyncConnectorJob::class, 1);
    Bus::assertDispatched(SyncConnectorJob::class, fn ($job) => $job->connector->is($due));
});
