<?php

use App\Models\DocumentStorageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.document-storage.edit'))->assertRedirect(route('login'));
});

test('admin can view the document storage settings page', function () {
    $this->actingAs(adminUser())->get(route('admin.document-storage.edit'))->assertOk();
});

test('the settings default to local storage', function () {
    expect(DocumentStorageSetting::current()->type->value)->toBe('local');
});

test('admin can switch to an s3 bucket', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->put(route('admin.document-storage.update'), [
        'type' => 's3',
        'key' => 'AKIA123',
        'secret' => 'shh',
        'region' => 'eu-west-1',
        'bucket' => 'documenti-crm',
    ]);

    $response->assertRedirect(route('admin.document-storage.edit'));
    $setting = DocumentStorageSetting::current();
    expect($setting->type->value)->toBe('s3');
    expect($setting->config['key'])->toBe('AKIA123');
    expect($setting->config['secret'])->toBe('shh');
    expect($setting->config['bucket'])->toBe('documenti-crm');
});

test('s3 storage requires key region and bucket', function () {
    $response = $this->actingAs(adminUser())->put(route('admin.document-storage.update'), [
        'type' => 's3',
    ]);

    $response->assertSessionHasErrors(['key', 'region', 'bucket']);
});

test('admin can switch to an sftp server', function () {
    $response = $this->actingAs(adminUser())->put(route('admin.document-storage.update'), [
        'type' => 'sftp',
        'sftp_host' => 'sftp.example.com',
        'sftp_port' => 2222,
        'sftp_username' => 'crm',
        'sftp_password' => 'secret',
        'sftp_root' => '/documenti',
    ]);

    $response->assertRedirect(route('admin.document-storage.edit'));
    $setting = DocumentStorageSetting::current();
    expect($setting->type->value)->toBe('sftp');
    expect($setting->config['host'])->toBe('sftp.example.com');
    expect($setting->config['port'])->toBe(2222);
    expect($setting->config['password'])->toBe('secret');
    expect($setting->config['root'])->toBe('/documenti');
});

test('sftp storage requires host and username', function () {
    $response = $this->actingAs(adminUser())->put(route('admin.document-storage.update'), [
        'type' => 'sftp',
    ]);

    $response->assertSessionHasErrors(['sftp_host', 'sftp_username']);
});

test('blank secret on update keeps the previously stored one', function () {
    $admin = adminUser();
    $setting = DocumentStorageSetting::create([
        'type' => 's3',
        'config' => ['key' => 'AKIA123', 'secret' => 'original-secret', 'region' => 'eu-west-1', 'bucket' => 'documenti-crm'],
    ]);

    $response = $this->actingAs($admin)->put(route('admin.document-storage.update'), [
        'type' => 's3',
        'key' => 'AKIA123',
        'secret' => '',
        'region' => 'eu-west-1',
        'bucket' => 'documenti-crm-rinominato',
    ]);

    $response->assertRedirect(route('admin.document-storage.edit'));
    $fresh = $setting->fresh();
    expect($fresh->config['secret'])->toBe('original-secret');
    expect($fresh->config['bucket'])->toBe('documenti-crm-rinominato');
});

test('the edit form prefills the real saved config, not just hardcoded defaults', function () {
    // Regression test: the form used to build its field defaults via
    // old(null, $setting->config ?? []) — Laravel's old() ignores the
    // given default entirely when the key is null, always returning the
    // (empty, absent a validation-error redirect) flashed old-input
    // bucket instead. Every field silently read as unset. See
    // edit.blade.php's $config assignment.
    $admin = adminUser();
    DocumentStorageSetting::create([
        'type' => 'sftp',
        'config' => ['host' => 'sftp.example.com', 'port' => 2222, 'username' => 'crm', 'password' => 'secret', 'root' => '/documenti'],
    ]);

    $response = $this->actingAs($admin)->get(route('admin.document-storage.edit'));

    $response->assertOk()
        ->assertSee('value="sftp.example.com"', false)
        ->assertSee('value="crm"', false)
        ->assertSee('value="/documenti"', false);
});

test('admin can switch back to local storage', function () {
    DocumentStorageSetting::create(['type' => 's3', 'config' => ['key' => 'x', 'secret' => 'y', 'region' => 'z', 'bucket' => 'w']]);

    $response = $this->actingAs(adminUser())->put(route('admin.document-storage.update'), ['type' => 'local']);

    $response->assertRedirect(route('admin.document-storage.edit'));
    expect(DocumentStorageSetting::current()->type->value)->toBe('local');
});
