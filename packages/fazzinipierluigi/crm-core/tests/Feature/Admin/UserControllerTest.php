<?php

use Fazzinipierluigi\CrmCore\Models\LoginProvider;
use Fazzinipierluigi\CrmCore\Tests\Fixtures\User;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('users without privileges are forbidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
});

test('admin can view the users index', function () {
    $this->actingAs(adminUser())->get(route('admin.users.index'))->assertOk();
});

test('admin can view the create user form', function () {
    $this->actingAs(adminUser())->get(route('admin.users.create'))->assertOk();
});

test('the roles field is a multi-select listing every role', function () {
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);

    $response = $this->actingAs(adminUser())->get(route('admin.users.create'));

    $response->assertOk();
    $response->assertSee('<select', false);
    $response->assertSee('id="roles"', false);
    $response->assertSee('name="roles[]"', false);
    $response->assertSee('multiple', false);
    $response->assertSee('<option value="'.$role->id.'"', false);
});

test('the edit form pre-selects the users current roles in the multi-select', function () {
    $admin = adminUser();
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->actingAs($admin)->get(route('admin.users.edit', $user));

    $response->assertOk();
    $response->assertSee('<option value="'.$role->id.'" selected>', false);
});

test('admin can create a user with roles', function () {
    $admin = adminUser();
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Jane Doe',
        'username' => 'janedoe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'roles' => [$role->id],
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $user = User::where('username', 'janedoe')->firstOrFail();
    expect($user->hasRole('editor'))->toBeTrue();
});

test('creating a user requires unique username and email', function () {
    $admin = adminUser();
    User::factory()->create(['username' => 'taken', 'email' => 'taken@example.com']);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Jane Doe',
        'username' => 'taken',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors(['username', 'email']);
});

test('admin can view the edit user form', function () {
    $admin = adminUser();
    $user = User::factory()->create();

    $this->actingAs($admin)->get(route('admin.users.edit', $user))->assertOk();
});

test('admin can update a user', function () {
    $admin = adminUser();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => 'Updated Name',
        'username' => $user->username,
        'email' => $user->email,
        'roles' => [],
    ]);

    $response->assertRedirect(route('admin.users.index'));
    expect($user->refresh()->name)->toBe('Updated Name');
});

test('admin cannot delete their own account', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

    $response->assertRedirect();
    expect(User::find($admin->id))->not->toBeNull();
});

test('admin can delete another user', function () {
    $admin = adminUser();
    $user = User::factory()->create();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $user));

    expect(User::find($user->id))->toBeNull();
});

test('admin can create a user linked to an ldap provider', function () {
    $admin = adminUser();
    $ldap = LoginProvider::create(['type' => 'ldap', 'name' => 'Corporate LDAP', 'slug' => 'corporate-ldap']);

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Jane Doe',
        'username' => 'janedoe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'login_provider_id' => $ldap->id,
        'provider_identifier' => 'uid=janedoe,dc=example,dc=com',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $user = User::where('username', 'janedoe')->firstOrFail();
    expect($user->login_provider_id)->toBe($ldap->id);
    expect($user->provider_identifier)->toBe('uid=janedoe,dc=example,dc=com');
});

test('a user with no login provider selected defaults to local on read', function () {
    LoginProvider::create(['type' => 'local', 'name' => 'Locale', 'slug' => 'local', 'is_system' => true]);
    $user = User::factory()->create(['login_provider_id' => null]);

    expect($user->effectiveLoginProvider()->slug)->toBe('local');
});

test('admin can change a users login provider', function () {
    $admin = adminUser();
    $user = User::factory()->create();
    $ldap = LoginProvider::create(['type' => 'ldap', 'name' => 'Corporate LDAP', 'slug' => 'corporate-ldap']);

    $response = $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
        'login_provider_id' => $ldap->id,
        'provider_identifier' => 'uid=someone,dc=example,dc=com',
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $fresh = $user->fresh();
    expect($fresh->login_provider_id)->toBe($ldap->id);
    expect($fresh->provider_identifier)->toBe('uid=someone,dc=example,dc=com');
});

test('login_provider_id must reference an existing provider', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Jane Doe',
        'username' => 'janedoe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'login_provider_id' => 99999,
    ]);

    $response->assertSessionHasErrors('login_provider_id');
});

test('users datatable endpoint returns json data', function () {
    $admin = adminUser();
    User::factory()->create(['name' => 'Findable User']);

    $response = $this->actingAs($admin)->getJson(route('admin.users.data', ['start' => 0, 'limit' => 25]));

    $response->assertOk()->assertJsonStructure(['data', 'total']);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Findable User');
});
