<?php

use App\Models\Connector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.connectors.index'))->assertRedirect(route('login'));
});

test('admin can view the connectors index', function () {
    $this->actingAs(adminUser())->get(route('admin.connectors.index'))->assertOk();
});

test('admin can create an exchange graph connector', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.connectors.store'), [
        'name' => 'Outlook 365',
        'type' => 'exchange_graph',
        'is_active' => '1',
        'sync_direction' => 'bidirectional',
        'sync_interval_minutes' => 15,
        'tenant_id' => 'tenant-123',
        'client_id' => 'client-abc',
        'client_secret' => 'shh',
    ]);

    $response->assertRedirect(route('admin.connectors.index'));
    $connector = Connector::where('name', 'Outlook 365')->firstOrFail();
    expect($connector->slug)->toBe('outlook-365');
    expect($connector->type->value)->toBe('exchange_graph');
    expect($connector->config['tenant_id'])->toBe('tenant-123');
    expect($connector->config['client_secret'])->toBe('shh');
});

test('exchange graph connector requires tenant client id and secret', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.connectors.store'), [
        'name' => 'Outlook 365',
        'type' => 'exchange_graph',
        'sync_direction' => 'bidirectional',
        'sync_interval_minutes' => 15,
    ]);

    $response->assertSessionHasErrors(['tenant_id', 'client_id', 'client_secret']);
});

test('admin can create an exchange ews connector', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.connectors.store'), [
        'name' => 'Exchange On-Prem',
        'type' => 'exchange_ews',
        'is_active' => '1',
        'sync_direction' => 'import_only',
        'sync_interval_minutes' => 30,
        'ews_url' => 'https://mail.example.com/EWS/Exchange.asmx',
        'username' => 'svc-calendar',
        'password' => 'secret',
    ]);

    $response->assertRedirect(route('admin.connectors.index'));
    $connector = Connector::where('name', 'Exchange On-Prem')->firstOrFail();
    expect($connector->config['ews_url'])->toBe('https://mail.example.com/EWS/Exchange.asmx');
    expect($connector->config['password'])->toBe('secret');
});

test('exchange ews connector requires url username and password', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.connectors.store'), [
        'name' => 'Exchange On-Prem',
        'type' => 'exchange_ews',
        'sync_direction' => 'bidirectional',
        'sync_interval_minutes' => 15,
    ]);

    $response->assertSessionHasErrors(['ews_url', 'username', 'password']);
});

test('admin can update a connector and blank secret keeps the previous value', function () {
    $admin = adminUser();
    $connector = Connector::create([
        'type' => 'exchange_graph',
        'name' => 'Outlook 365',
        'slug' => 'outlook-365',
        'is_active' => true,
        'sync_direction' => 'bidirectional',
        'sync_interval_minutes' => 15,
        'config' => ['tenant_id' => 'tenant-123', 'client_id' => 'client-abc', 'client_secret' => 'original-secret'],
    ]);

    $response = $this->actingAs($admin)->put(route('admin.connectors.update', $connector), [
        'name' => 'Outlook 365 (rinominato)',
        'type' => 'exchange_graph',
        'is_active' => '1',
        'sync_direction' => 'import_only',
        'sync_interval_minutes' => 45,
        'tenant_id' => 'tenant-123',
        'client_id' => 'client-abc',
        'client_secret' => '',
    ]);

    $response->assertRedirect(route('admin.connectors.index'));
    $fresh = $connector->fresh();
    expect($fresh->name)->toBe('Outlook 365 (rinominato)');
    expect($fresh->sync_direction->value)->toBe('import_only');
    expect($fresh->sync_interval_minutes)->toBe(45);
    expect($fresh->config['client_secret'])->toBe('original-secret');
});

test('a connector can be deleted', function () {
    $admin = adminUser();
    $connector = Connector::create([
        'type' => 'exchange_graph', 'name' => 'Da eliminare', 'slug' => 'da-eliminare',
        'sync_direction' => 'bidirectional', 'sync_interval_minutes' => 15,
    ]);

    $this->actingAs($admin)->delete(route('admin.connectors.destroy', $connector));

    expect(Connector::find($connector->id))->toBeNull();
});

test('connectors datatable endpoint returns json data', function () {
    $admin = adminUser();
    Connector::create([
        'type' => 'exchange_graph', 'name' => 'Findable Connector', 'slug' => 'findable-connector',
        'sync_direction' => 'bidirectional', 'sync_interval_minutes' => 15,
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.connectors.data', ['start' => 0, 'limit' => 25]));

    $response->assertOk()->assertJsonStructure(['data', 'total']);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Findable Connector');
});
