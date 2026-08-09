<?php

use App\Models\MailConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.mail-connectors.index'))->assertRedirect(route('login'));
});

test('admin can view the mail connectors index', function () {
    $this->actingAs(adminUser())->get(route('admin.mail-connectors.index'))->assertOk();
});

test('admin can create an exchange graph mail connector', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.mail-connectors.store'), [
        'name' => 'Microsoft 365 aziendale',
        'type' => 'exchange_graph',
        'is_active' => '1',
        'tenant_id' => 'tenant-123',
        'client_id' => 'client-abc',
        'client_secret' => 'shh',
    ]);

    $response->assertRedirect(route('admin.mail-connectors.index'));
    $connector = MailConnector::where('name', 'Microsoft 365 aziendale')->firstOrFail();
    expect($connector->slug)->toBe('microsoft-365-aziendale');
    expect($connector->type->value)->toBe('exchange_graph');
    expect($connector->config['tenant_id'])->toBe('tenant-123');
    expect($connector->config['client_secret'])->toBe('shh');
});

test('exchange graph mail connector requires tenant client id and secret', function () {
    $response = $this->actingAs(adminUser())->post(route('admin.mail-connectors.store'), [
        'name' => 'Microsoft 365 aziendale',
        'type' => 'exchange_graph',
    ]);

    $response->assertSessionHasErrors(['tenant_id', 'client_id', 'client_secret']);
});

test('admin can create an exchange ews mail connector', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.mail-connectors.store'), [
        'name' => 'Exchange On-Prem',
        'type' => 'exchange_ews',
        'is_active' => '1',
        'ews_url' => 'https://mail.example.com/EWS/Exchange.asmx',
        'username' => 'svc-mail',
        'password' => 'secret',
    ]);

    $response->assertRedirect(route('admin.mail-connectors.index'));
    $connector = MailConnector::where('name', 'Exchange On-Prem')->firstOrFail();
    expect($connector->config['ews_url'])->toBe('https://mail.example.com/EWS/Exchange.asmx');
    expect($connector->config['password'])->toBe('secret');
});

test('exchange ews mail connector requires url username and password', function () {
    $response = $this->actingAs(adminUser())->post(route('admin.mail-connectors.store'), [
        'name' => 'Exchange On-Prem',
        'type' => 'exchange_ews',
    ]);

    $response->assertSessionHasErrors(['ews_url', 'username', 'password']);
});

test('admin can update a mail connector and blank secret keeps the previous value', function () {
    $admin = adminUser();
    $connector = MailConnector::create([
        'type' => 'exchange_graph',
        'name' => 'Microsoft 365 aziendale',
        'slug' => 'microsoft-365-aziendale',
        'is_active' => true,
        'config' => ['tenant_id' => 'tenant-123', 'client_id' => 'client-abc', 'client_secret' => 'original-secret'],
    ]);

    $response = $this->actingAs($admin)->put(route('admin.mail-connectors.update', $connector), [
        'name' => 'Microsoft 365 (rinominato)',
        'type' => 'exchange_graph',
        'is_active' => '1',
        'tenant_id' => 'tenant-123',
        'client_id' => 'client-abc',
        'client_secret' => '',
    ]);

    $response->assertRedirect(route('admin.mail-connectors.index'));
    $fresh = $connector->fresh();
    expect($fresh->name)->toBe('Microsoft 365 (rinominato)');
    expect($fresh->config['client_secret'])->toBe('original-secret');
});

test('a mail connector can be deleted', function () {
    $admin = adminUser();
    $connector = MailConnector::create([
        'type' => 'exchange_graph', 'name' => 'Da eliminare', 'slug' => 'da-eliminare',
    ]);

    $this->actingAs($admin)->delete(route('admin.mail-connectors.destroy', $connector));

    expect(MailConnector::find($connector->id))->toBeNull();
});

test('mail connectors datatable endpoint returns json data', function () {
    $admin = adminUser();
    MailConnector::create([
        'type' => 'exchange_graph', 'name' => 'Findable Connector', 'slug' => 'findable-mail-connector',
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.mail-connectors.data', ['start' => 0, 'limit' => 25]));

    $response->assertOk()->assertJsonStructure(['data', 'total']);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Findable Connector');
});
