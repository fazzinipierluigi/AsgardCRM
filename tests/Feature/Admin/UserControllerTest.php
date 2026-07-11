<?php

use App\Models\User;
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

test('users datatable endpoint returns json data', function () {
    $admin = adminUser();
    User::factory()->create(['name' => 'Findable User']);

    $response = $this->actingAs($admin)->getJson(route('admin.users.data', ['start' => 0, 'limit' => 25]));

    $response->assertOk()->assertJsonStructure(['data', 'total']);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Findable User');
});
