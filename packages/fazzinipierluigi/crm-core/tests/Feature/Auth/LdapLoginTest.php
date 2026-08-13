<?php

use Fazzinipierluigi\CrmCore\Models\LoginProvider;
use Fazzinipierluigi\CrmCore\Services\Auth\LdapAuthenticator;
use Fazzinipierluigi\CrmCore\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Testing\LdapFake;

uses(RefreshDatabase::class);

afterEach(function () {
    DirectoryEmulator::tearDown();
});

/**
 * Create an active LDAP provider and register + fake its connection in the
 * LdapRecord container, ready for query()->insert()/first() and bind
 * expectations.
 */
function fakeLdapProvider(): LoginProvider
{
    $provider = LoginProvider::create([
        'type' => 'ldap',
        'name' => 'Corporate LDAP',
        'slug' => 'corporate-ldap',
        'is_active' => true,
        'config' => [
            'base_dn' => 'dc=local,dc=com',
            'user_filter' => '(uid=%s)',
            'attr_username' => 'uid',
            'attr_email' => 'mail',
            'attr_name' => 'cn',
        ],
    ]);

    $name = LdapAuthenticator::connectionName($provider);
    Container::addConnection(new Connection(['base_dn' => 'dc=local,dc=com']), $name);
    DirectoryEmulator::setup($name);

    return $provider;
}

test('ldap user can log in with the correct password and syncs directory attributes', function () {
    $provider = fakeLdapProvider();
    $name = LdapAuthenticator::connectionName($provider);
    $dn = 'uid=jdoe,dc=local,dc=com';

    Container::getConnection($name)->query()->insert($dn, [
        'uid' => ['jdoe'],
        'mail' => ['jdoe@example.com'],
        'cn' => ['John Doe'],
        'objectclass' => ['inetOrgPerson'],
    ]);

    $user = User::factory()->create(['username' => 'jdoe', 'login_provider_id' => $provider->id]);

    Container::getConnection($name)->getLdapConnection()->expect(
        LdapFake::operation('bind')->once()->with($dn, 'correct-password')->andReturnResponse()
    );

    $response = $this->post('/login', ['username' => 'jdoe', 'password' => 'correct-password']);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard'));
    expect($user->fresh()->email)->toBe('jdoe@example.com');
    expect($user->fresh()->name)->toBe('John Doe');
});

test('ldap user with the wrong password is rejected', function () {
    $provider = fakeLdapProvider();
    $name = LdapAuthenticator::connectionName($provider);
    $dn = 'uid=jdoe,dc=local,dc=com';

    Container::getConnection($name)->query()->insert($dn, [
        'uid' => ['jdoe'],
        'objectclass' => ['inetOrgPerson'],
    ]);

    User::factory()->create(['username' => 'jdoe', 'login_provider_id' => $provider->id]);

    Container::getConnection($name)->getLdapConnection()->expect(
        LdapFake::operation('bind')->once()->with($dn, 'wrong-password')->andReturnErrorResponse()
    );

    $response = $this->post('/login', ['username' => 'jdoe', 'password' => 'wrong-password']);

    $this->assertGuest();
    $response->assertSessionHasErrors('username');
});

test('ldap login fails cleanly when the username has no matching directory entry', function () {
    $provider = fakeLdapProvider();

    User::factory()->create(['username' => 'ghost', 'login_provider_id' => $provider->id]);

    $response = $this->post('/login', ['username' => 'ghost', 'password' => 'whatever']);

    $this->assertGuest();
    $response->assertSessionHasErrors('username');
});

test('a user with no login provider is unaffected by ldap providers existing', function () {
    fakeLdapProvider();

    $user = User::factory()->create(['username' => 'localuser', 'password' => bcrypt('password')]);

    $response = $this->post('/login', ['username' => 'localuser', 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard'));
});
