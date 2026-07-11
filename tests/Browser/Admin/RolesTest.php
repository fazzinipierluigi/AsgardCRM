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
            ->type('slug', 'editor')
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

        // The sidebar also has a "Permessi" menu entry, so scope the click
        // to the grid's own row action instead of clickLink() on the whole page.
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
