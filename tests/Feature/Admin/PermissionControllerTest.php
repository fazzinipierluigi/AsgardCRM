<?php

use Fazzinipierluigi\JustAGate\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.permissions.index'))->assertRedirect(route('login'));
});

test('admin can view the permissions index', function () {
    $this->actingAs(adminUser())->get(route('admin.permissions.index'))->assertOk();
});

test('admin can create a permission', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.permissions.store'), [
        'key' => 'contacts.manage',
        'name' => 'Manage Contacts',
    ]);

    $response->assertRedirect(route('admin.permissions.index'));
    expect(Permission::where('key', 'contacts.manage')->exists())->toBeTrue();
});

test('creating a permission requires a unique key', function () {
    $admin = adminUser();
    Permission::create(['key' => 'contacts.manage']);

    $response = $this->actingAs($admin)->post(route('admin.permissions.store'), [
        'key' => 'contacts.manage',
    ]);

    $response->assertSessionHasErrors('key');
});

test('admin can update a permission', function () {
    $admin = adminUser();
    $permission = Permission::create(['key' => 'contacts.manage', 'name' => 'Manage Contacts']);

    $response = $this->actingAs($admin)->put(route('admin.permissions.update', $permission), [
        'key' => 'contacts.manage',
        'name' => 'Updated Name',
    ]);

    $response->assertRedirect(route('admin.permissions.index'));
    expect($permission->fresh()->name)->toBe('Updated Name');
});

test('admin can delete a permission', function () {
    $admin = adminUser();
    $permission = Permission::create(['key' => 'contacts.manage']);

    $this->actingAs($admin)->delete(route('admin.permissions.destroy', $permission));

    expect(Permission::find($permission->id))->toBeNull();
});

test('permissions datatable endpoint returns json data', function () {
    $admin = adminUser();
    Permission::create(['key' => 'findable.key', 'name' => 'Findable Permission']);

    $response = $this->actingAs($admin)->getJson(route('admin.permissions.data', ['start' => 0, 'limit' => 25]));

    $response->assertOk()->assertJsonStructure(['data', 'total']);
    expect(collect($response->json('data'))->pluck('key'))->toContain('findable.key');
});
