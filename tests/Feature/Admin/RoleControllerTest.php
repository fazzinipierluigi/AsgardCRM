<?php

use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.roles.index'))->assertRedirect(route('login'));
});

test('admin can view the roles index', function () {
    $this->actingAs(adminUser())->get(route('admin.roles.index'))->assertOk();
});

test('admin can create a role with permissions', function () {
    $admin = adminUser();
    $permission = Permission::create(['key' => 'contacts.manage', 'name' => 'Manage Contacts']);

    $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Editor',
        'slug' => 'editor',
        'permissions' => [$permission->key],
    ]);

    $response->assertRedirect(route('admin.roles.index'));
    $role = Role::where('slug', 'editor')->firstOrFail();
    expect($role->hasPermission('contacts.manage'))->toBeTrue();
});

test('creating a role requires a unique slug', function () {
    $admin = adminUser();
    Role::create(['name' => 'Editor', 'slug' => 'editor']);

    $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Editor 2',
        'slug' => 'editor',
    ]);

    $response->assertSessionHasErrors('slug');
});

test('admin can update a role permissions', function () {
    $admin = adminUser();
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $permission = Permission::create(['key' => 'contacts.manage', 'name' => 'Manage Contacts']);

    $response = $this->actingAs($admin)->put(route('admin.roles.update', $role), [
        'name' => 'Editor',
        'slug' => 'editor',
        'permissions' => [$permission->key],
    ]);

    $response->assertRedirect(route('admin.roles.index'));
    expect($role->fresh()->hasPermission('contacts.manage'))->toBeTrue();
});

test('system role slug cannot be changed', function () {
    $admin = adminUser();
    $adminRole = Role::where('slug', 'admin')->firstOrFail();

    $this->actingAs($admin)->put(route('admin.roles.update', $adminRole), [
        'name' => 'Administrator',
        'slug' => 'super-admin',
    ]);

    expect($adminRole->fresh()->slug)->toBe('admin');
});

test('system role cannot be deleted', function () {
    $admin = adminUser();
    $adminRole = Role::where('slug', 'admin')->firstOrFail();

    $response = $this->actingAs($admin)->delete(route('admin.roles.destroy', $adminRole));

    $response->assertRedirect();
    expect(Role::find($adminRole->id))->not->toBeNull();
});

test('non system role can be deleted', function () {
    $admin = adminUser();
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role));

    expect(Role::find($role->id))->toBeNull();
});

test('roles datatable endpoint returns json data', function () {
    $admin = adminUser();
    Role::create(['name' => 'Findable Role', 'slug' => 'findable-role']);

    $response = $this->actingAs($admin)->getJson(route('admin.roles.data', ['start' => 0, 'limit' => 25]));

    $response->assertOk()->assertJsonStructure(['data', 'total']);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Findable Role');
});
