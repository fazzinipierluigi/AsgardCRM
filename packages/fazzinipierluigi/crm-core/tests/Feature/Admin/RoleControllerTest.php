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

test('admin can create a role and its slug is auto-generated', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.roles.store'), [
        'name' => 'Editor',
    ]);

    $response->assertRedirect(route('admin.roles.index'));
    expect(Role::where('name', 'Editor')->firstOrFail()->slug)->toBe('editor');
});

test('auto-generated slugs are unique even for the same name', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'Editor']);
    $this->actingAs($admin)->post(route('admin.roles.store'), ['name' => 'Editor']);

    $slugs = Role::where('name', 'Editor')->pluck('slug')->sort()->values();
    expect($slugs->all())->toBe(['editor', 'editor-2']);
});

test('admin can update a role name', function () {
    $admin = adminUser();
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);

    $response = $this->actingAs($admin)->put(route('admin.roles.update', $role), [
        'name' => 'Senior Editor',
        'slug' => 'editor',
    ]);

    $response->assertRedirect(route('admin.roles.index'));
    expect($role->fresh()->name)->toBe('Senior Editor');
});

test('admin can view the role permissions form', function () {
    $admin = adminUser();
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);

    $this->actingAs($admin)->get(route('admin.roles.permissions.edit', $role))->assertOk();
});

test('admin can sync a role permissions', function () {
    $admin = adminUser();
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $permission = Permission::create(['key' => 'contacts.manage', 'name' => 'Manage Contacts']);

    $response = $this->actingAs($admin)->put(route('admin.roles.permissions.update', $role), [
        'permissions' => [$permission->key],
    ]);

    $response->assertRedirect(route('admin.roles.index'));
    expect($role->fresh()->hasPermission('contacts.manage'))->toBeTrue();
});

test('removing all checkboxes clears the role permissions', function () {
    $admin = adminUser();
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $permission = Permission::create(['key' => 'contacts.manage', 'name' => 'Manage Contacts']);
    $role->givePermission($permission);

    $this->actingAs($admin)->put(route('admin.roles.permissions.update', $role), []);

    expect($role->fresh()->hasPermission('contacts.manage'))->toBeFalse();
});

test('role permissions must exist', function () {
    $admin = adminUser();
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);

    $response = $this->actingAs($admin)->put(route('admin.roles.permissions.update', $role), [
        'permissions' => ['not-a-real-permission'],
    ]);

    $response->assertSessionHasErrors('permissions.0');
});

test('the admin role edit form is not reachable', function () {
    $admin = adminUser();
    $adminRole = Role::where('slug', 'admin')->firstOrFail();

    $response = $this->actingAs($admin)->get(route('admin.roles.edit', $adminRole));

    $response->assertRedirect(route('admin.roles.index'));
});

test('the admin role cannot be updated', function () {
    $admin = adminUser();
    $adminRole = Role::where('slug', 'admin')->firstOrFail();

    $response = $this->actingAs($admin)->put(route('admin.roles.update', $adminRole), [
        'name' => 'Renamed',
        'slug' => 'super-admin',
    ]);

    $response->assertRedirect();
    expect($adminRole->fresh()->name)->toBe('Administrator');
    expect($adminRole->fresh()->slug)->toBe('admin');
});

test('the admin role permissions form is not reachable', function () {
    $admin = adminUser();
    $adminRole = Role::where('slug', 'admin')->firstOrFail();

    $response = $this->actingAs($admin)->get(route('admin.roles.permissions.edit', $adminRole));

    $response->assertRedirect(route('admin.roles.index'));
});

test('permissions cannot be assigned to the admin role', function () {
    $admin = adminUser();
    $adminRole = Role::where('slug', 'admin')->firstOrFail();
    $permission = Permission::create(['key' => 'contacts.manage', 'name' => 'Manage Contacts']);

    $response = $this->actingAs($admin)->put(route('admin.roles.permissions.update', $adminRole), [
        'permissions' => [$permission->key],
    ]);

    $response->assertRedirect();
    expect($adminRole->fresh()->hasPermission('contacts.manage'))->toBeFalse();
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
