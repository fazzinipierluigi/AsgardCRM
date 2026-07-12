<?php

use App\Models\User;
use Fazzinipierluigi\JustAGate\Models\Role;
use Laravel\Dusk\Browser;

test('admin can create, edit and delete a user', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/users/create')
            ->type('name', 'Browser Created User')
            ->type('username', 'browsercreated')
            ->type('email', 'browsercreated@example.com')
            ->type('password', 'password123')
            ->type('password_confirmation', 'password123')
            ->press('Crea utente')
            ->waitForLocation('/admin/users')
            ->waitForText('browsercreated');

        // Narrow the grid down to a single row before clicking, since the
        // admin performing the action is also listed in the same table.
        // Scoped to the grid container: the admin's name is always visible
        // in the top navbar regardless of the grid's filter state.
        $browser->type('.rt-search-bar-input', 'browsercreated')
            ->within('#users-grid', function (Browser $grid) use ($admin) {
                $grid->waitUntilMissingText($admin->name, 10);
            });

        $browser->clickLink('Modifica')
            ->waitForText('Modifica utente')
            ->assertInputValue('username', 'browsercreated')
            ->type('name', 'Browser Updated User')
            ->press('Salva modifiche')
            ->waitForLocation('/admin/users')
            ->waitForText('Browser Updated User');

        $browser->type('.rt-search-bar-input', 'browsercreated')
            ->within('#users-grid', function (Browser $grid) use ($admin) {
                $grid->waitUntilMissingText($admin->name, 10);
            })
            ->press('Elimina')
            ->waitForLocation('/admin/users');
    });

    expect(User::where('username', 'browsercreated')->exists())->toBeFalse();
});

test('roles field is a tom select multi-select and picking two roles saves both', function () {
    $admin = adminUser();
    $editor = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $viewer = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);

    $this->browse(function (Browser $browser) use ($admin, $editor, $viewer) {
        $browser->loginAs($admin)
            ->visit('/admin/users/create')
            ->assertPresent('#roles ~ .ts-wrapper')
            ->type('name', 'Multi Role User')
            ->type('username', 'multirole')
            ->type('email', 'multirole@example.com')
            ->type('password', 'password123')
            ->type('password_confirmation', 'password123')
            ->click('#roles ~ .ts-wrapper .ts-control')
            ->waitFor('.ts-dropdown .option[data-value="'.$editor->id.'"]')
            ->click('.ts-dropdown .option[data-value="'.$editor->id.'"]')
            ->waitFor('.ts-dropdown .option[data-value="'.$viewer->id.'"]')
            ->click('.ts-dropdown .option[data-value="'.$viewer->id.'"]')
            ->assertSeeIn('#roles ~ .ts-wrapper', 'Editor')
            ->assertSeeIn('#roles ~ .ts-wrapper', 'Viewer')
            ->press('Crea utente')
            ->waitForLocation('/admin/users');
    });

    $user = User::where('username', 'multirole')->firstOrFail();
    expect($user->hasRole('editor'))->toBeTrue();
    expect($user->hasRole('viewer'))->toBeTrue();
});
