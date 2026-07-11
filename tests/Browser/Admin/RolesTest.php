<?php

use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Laravel\Dusk\Browser;

test('admin can create a role and assign a permission to it via the dedicated action', function () {
    $admin = adminUser();
    Permission::create(['key' => 'contacts.manage', 'name' => 'Manage Contacts']);

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/roles/create')
            ->type('name', 'Editor')
            ->press('Crea ruolo')
            ->waitForLocation('/admin/roles')
            ->waitForText('Editor');

        // Narrow the grid down to the role just created — "Administrator"
        // (created by adminUser()) is also listed and would otherwise be
        // the first "Permessi" link on the page.
        $browser->type('.rt-search-bar-input', 'Editor')
            ->within('#roles-grid', function (Browser $grid) {
                $grid->waitUntilMissingText('Administrator', 10);
            });

        // "Administrator" also has a "Permessi" row action, so scope the
        // click to the filtered grid instead of clickLink() on the whole page.
        $browser->within('#roles-grid', function (Browser $grid) {
            $grid->clickLink('Permessi');
        });

        $browser->waitForText('Manage Contacts')
            ->check('permissions[]')
            ->press('Salva permessi')
            ->waitForLocation('/admin/roles');
    });

    $role = Role::where('slug', 'editor')->firstOrFail();
    expect($role->hasPermission('contacts.manage'))->toBeTrue();
});

test('the admin role has no edit or permissions actions in the grid', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/roles')
            ->waitForText('Administrator')
            ->within('#roles-grid', function (Browser $grid) {
                $grid->assertDontSee('Modifica')
                    ->assertDontSee('Permessi');
            });
    });
});
