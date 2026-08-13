<?php

use Fazzinipierluigi\AsgardCRM\Jobs\SyncConnectorJob;
use Fazzinipierluigi\AsgardCRM\Models\Connector;
use Fazzinipierluigi\AsgardCRM\Services\Connectors\CalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

function jobConnector(): Connector
{
    return Connector::create([
        'type' => 'exchange_graph',
        'name' => 'Outlook 365',
        'slug' => 'outlook-365-'.uniqid(),
        'sync_direction' => 'bidirectional',
        'sync_interval_minutes' => 15,
    ]);
}

test('dispatching the job queues it without running it', function () {
    Queue::fake();
    $connector = jobConnector();

    SyncConnectorJob::dispatch($connector);

    Queue::assertPushed(SyncConnectorJob::class, fn ($job) => $job->connector->is($connector));
});

test('handling the job calls CalendarSyncService::syncConnector', function () {
    $connector = jobConnector();

    $this->mock(CalendarSyncService::class, function (MockInterface $mock) use ($connector) {
        $mock->shouldReceive('syncConnector')->once()->with(Mockery::on(fn ($c) => $c->is($connector)));
    });

    (new SyncConnectorJob($connector))->handle(app(CalendarSyncService::class));
});

test('failed() records the failure on the connector', function () {
    $connector = jobConnector();
    $job = new SyncConnectorJob($connector);

    $job->failed(new RuntimeException('Boom'));

    $fresh = $connector->fresh();
    expect($fresh->last_sync_status)->toBe('failed');
    expect($fresh->last_sync_message)->toBe('Boom');
});
