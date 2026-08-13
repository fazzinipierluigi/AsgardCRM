<?php

use Fazzinipierluigi\AsgardCRM\Models\Connector;
use Fazzinipierluigi\AsgardCRM\Models\ConnectorUserMailbox;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function testConnector(): Connector
{
    return Connector::create([
        'type' => 'exchange_graph', 'name' => 'Outlook 365', 'slug' => 'outlook-365',
        'sync_direction' => 'bidirectional', 'sync_interval_minutes' => 15,
    ]);
}

test('guests are redirected to login', function () {
    $connector = testConnector();

    $this->get(route('admin.connectors.mailboxes.edit', $connector))->assertRedirect(route('login'));
});

test('admin can view the mailbox mapping page', function () {
    $connector = testConnector();

    $this->actingAs(adminUser())->get(route('admin.connectors.mailboxes.edit', $connector))->assertOk();
});

test('admin can map a user to a mailbox', function () {
    $admin = adminUser();
    $connector = testConnector();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.connectors.mailboxes.update', $connector), [
        'mailboxes' => [$user->id => 'mario.rossi@example.com'],
    ]);

    $response->assertRedirect(route('admin.connectors.mailboxes.edit', $connector));
    expect(ConnectorUserMailbox::where('connector_id', $connector->id)->where('user_id', $user->id)->first()?->mailbox_email)
        ->toBe('mario.rossi@example.com');
});

test('clearing a mailbox email removes the mapping', function () {
    $admin = adminUser();
    $connector = testConnector();
    $user = User::factory()->create();
    ConnectorUserMailbox::create(['connector_id' => $connector->id, 'user_id' => $user->id, 'mailbox_email' => 'mario.rossi@example.com']);

    $this->actingAs($admin)->put(route('admin.connectors.mailboxes.update', $connector), [
        'mailboxes' => [$user->id => ''],
    ]);

    expect(ConnectorUserMailbox::where('connector_id', $connector->id)->where('user_id', $user->id)->exists())->toBeFalse();
});

test('an invalid mailbox email is rejected', function () {
    $admin = adminUser();
    $connector = testConnector();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.connectors.mailboxes.update', $connector), [
        'mailboxes' => [$user->id => 'not-an-email'],
    ]);

    $response->assertSessionHasErrors("mailboxes.{$user->id}");
});
