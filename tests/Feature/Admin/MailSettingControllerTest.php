<?php

use App\Models\MailSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.mail-settings.edit'))->assertRedirect(route('login'));
});

test('admin can view the mail settings page', function () {
    $this->actingAs(adminUser())->get(route('admin.mail-settings.edit'))->assertOk();
});

test('the settings default to every protocol enabled', function () {
    expect(MailSetting::current()->enabled_protocols)->toEqualCanonicalizing(['imap', 'pop3', 'exchange']);
});

test('a freshly created singleton has every numeric default populated, not null', function () {
    $setting = MailSetting::current();

    expect($setting->connection_timeout_seconds)->toBe(10);
    expect($setting->max_attachment_size_kb)->toBe(25600);
    expect($setting->cache_ttl_seconds)->toBe(60);
});

test('admin can update the global mail policy', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->put(route('admin.mail-settings.update'), [
        'connection_timeout_seconds' => 20,
        'max_attachment_size_kb' => 10240,
        'cache_ttl_seconds' => 30,
        'enabled_protocols' => ['imap'],
    ]);

    $response->assertRedirect(route('admin.mail-settings.edit'));
    $setting = MailSetting::current();
    expect($setting->connection_timeout_seconds)->toBe(20);
    expect($setting->max_attachment_size_kb)->toBe(10240);
    expect($setting->cache_ttl_seconds)->toBe(30);
    expect($setting->enabled_protocols)->toBe(['imap']);
});

test('enabled_protocols requires at least one protocol', function () {
    $response = $this->actingAs(adminUser())->put(route('admin.mail-settings.update'), [
        'connection_timeout_seconds' => 10,
        'max_attachment_size_kb' => 25600,
        'cache_ttl_seconds' => 60,
        'enabled_protocols' => [],
    ]);

    $response->assertSessionHasErrors(['enabled_protocols']);
});

test('a blank oauth client secret keeps the previously stored one', function () {
    $admin = adminUser();
    $baseParams = [
        'connection_timeout_seconds' => 10,
        'max_attachment_size_kb' => 25600,
        'cache_ttl_seconds' => 60,
        'enabled_protocols' => ['imap'],
    ];

    $this->actingAs($admin)->put(route('admin.mail-settings.update'), $baseParams + [
        'google_oauth_client_id' => 'client-123.apps.googleusercontent.com',
        'google_oauth_client_secret' => 'super-secret',
    ]);

    expect(MailSetting::current()->google_oauth_client_secret)->toBe('super-secret');

    // A later save that leaves the secret field blank (e.g. only
    // changing the client id) must not wipe it out.
    $this->actingAs($admin)->put(route('admin.mail-settings.update'), $baseParams + [
        'google_oauth_client_id' => 'client-456.apps.googleusercontent.com',
        'google_oauth_client_secret' => '',
    ]);

    $setting = MailSetting::current();
    expect($setting->google_oauth_client_id)->toBe('client-456.apps.googleusercontent.com');
    expect($setting->google_oauth_client_secret)->toBe('super-secret');
});
