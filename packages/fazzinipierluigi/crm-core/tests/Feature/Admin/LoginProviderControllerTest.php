<?php

use Fazzinipierluigi\AsgardCRM\Models\LoginProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function localProvider(): LoginProvider
{
    return LoginProvider::create([
        'type' => 'local',
        'name' => 'Locale',
        'slug' => 'local',
        'is_active' => true,
        'is_system' => true,
    ]);
}

test('guests are redirected to login', function () {
    $this->get(route('admin.login-providers.index'))->assertRedirect(route('login'));
});

test('admin can view the login providers index', function () {
    $this->actingAs(adminUser())->get(route('admin.login-providers.index'))->assertOk();
});

test('admin can create an ldap provider', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.login-providers.store'), [
        'name' => 'Corporate LDAP',
        'type' => 'ldap',
        'is_active' => '1',
        'host' => 'ldap.example.com',
        'port' => 389,
        'base_dn' => 'dc=example,dc=com',
        'bind_dn' => 'cn=admin,dc=example,dc=com',
        'bind_password' => 'secret',
        'user_filter' => '(uid=%s)',
    ]);

    $response->assertRedirect(route('admin.login-providers.index'));
    $provider = LoginProvider::where('name', 'Corporate LDAP')->firstOrFail();
    expect($provider->slug)->toBe('corporate-ldap');
    expect($provider->type)->toBe('ldap');
    expect($provider->config['host'])->toBe('ldap.example.com');
    expect($provider->config['bind_password'])->toBe('secret');
});

test('ldap provider requires host base_dn and port', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.login-providers.store'), [
        'name' => 'Corporate LDAP',
        'type' => 'ldap',
    ]);

    $response->assertSessionHasErrors(['host', 'port', 'base_dn']);
});

test('oauth provider requires client credentials and endpoints', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.login-providers.store'), [
        'name' => 'Google',
        'type' => 'oauth',
    ]);

    $response->assertSessionHasErrors(['client_id', 'client_secret', 'authorize_url', 'token_url', 'userinfo_url']);
});

test('admin can create an oauth provider', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.login-providers.store'), [
        'name' => 'Google',
        'type' => 'oauth',
        'is_active' => '1',
        'client_id' => 'abc',
        'client_secret' => 'shh',
        'authorize_url' => 'https://idp.example.com/authorize',
        'token_url' => 'https://idp.example.com/token',
        'userinfo_url' => 'https://idp.example.com/userinfo',
        'scopes' => 'openid email',
    ]);

    $response->assertRedirect(route('admin.login-providers.index'));
    $provider = LoginProvider::where('name', 'Google')->firstOrFail();
    expect($provider->config['client_id'])->toBe('abc');
});

test('saml provider requires idp entity id sso url and certificate', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.login-providers.store'), [
        'name' => 'Okta',
        'type' => 'saml',
    ]);

    $response->assertSessionHasErrors(['idp_entity_id', 'idp_sso_url', 'idp_x509_cert']);
});

test('admin can update a provider and blank secret keeps the previous value', function () {
    $admin = adminUser();
    $provider = LoginProvider::create([
        'type' => 'oauth',
        'name' => 'Google',
        'slug' => 'google',
        'is_active' => true,
        'config' => [
            'client_id' => 'abc',
            'client_secret' => 'original-secret',
            'authorize_url' => 'https://idp.example.com/authorize',
            'token_url' => 'https://idp.example.com/token',
            'userinfo_url' => 'https://idp.example.com/userinfo',
        ],
    ]);

    $response = $this->actingAs($admin)->put(route('admin.login-providers.update', $provider), [
        'name' => 'Google Workspace',
        'type' => 'oauth',
        'is_active' => '1',
        'client_id' => 'abc',
        'client_secret' => '',
        'authorize_url' => 'https://idp.example.com/authorize',
        'token_url' => 'https://idp.example.com/token',
        'userinfo_url' => 'https://idp.example.com/userinfo',
    ]);

    $response->assertRedirect(route('admin.login-providers.index'));
    $fresh = $provider->fresh();
    expect($fresh->name)->toBe('Google Workspace');
    expect($fresh->config['client_secret'])->toBe('original-secret');
});

test('local provider edit form is not reachable', function () {
    $admin = adminUser();
    $local = localProvider();

    $response = $this->actingAs($admin)->get(route('admin.login-providers.edit', $local));

    $response->assertRedirect(route('admin.login-providers.index'));
});

test('local provider cannot be updated', function () {
    $admin = adminUser();
    $local = localProvider();

    $response = $this->actingAs($admin)->put(route('admin.login-providers.update', $local), [
        'name' => 'Renamed',
        'type' => 'local',
    ]);

    $response->assertRedirect();
    expect($local->fresh()->name)->toBe('Locale');
});

test('local provider cannot be deleted', function () {
    $admin = adminUser();
    $local = localProvider();

    $response = $this->actingAs($admin)->delete(route('admin.login-providers.destroy', $local));

    $response->assertRedirect();
    expect(LoginProvider::find($local->id))->not->toBeNull();
});

test('non system provider can be deleted', function () {
    $admin = adminUser();
    $provider = LoginProvider::create(['type' => 'ldap', 'name' => 'Old LDAP', 'slug' => 'old-ldap']);

    $this->actingAs($admin)->delete(route('admin.login-providers.destroy', $provider));

    expect(LoginProvider::find($provider->id))->toBeNull();
});

test('login providers datatable endpoint returns json data', function () {
    $admin = adminUser();
    LoginProvider::create(['type' => 'ldap', 'name' => 'Findable Provider', 'slug' => 'findable-provider']);

    $response = $this->actingAs($admin)->getJson(route('admin.login-providers.data', ['start' => 0, 'limit' => 25]));

    $response->assertOk()->assertJsonStructure(['data', 'total']);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Findable Provider');
});
