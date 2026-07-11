<?php

use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Laravel\Dusk\Browser;

test('admin can create a role and assign a permission to it', function () {
    $admin = adminUser();
    Permission::create(['key' => 'contacts.manage', 'name' => 'Manage Contacts']);

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/roles/create')
            ->type('name', 'Editor')
            ->type('slug', 'editor')
            ->check('permissions[]')
            ->press('Crea ruolo')
            ->waitForLocation('/admin/roles');
    });

    $role = Role::where('slug', 'editor')->firstOrFail();
    expect($role->hasPermission('contacts.manage'))->toBeTrue();
});
