<?php

use Laravel\Dusk\Browser;

test('users grid follows the admin theme preference', function () {
    $admin = adminUser();
    $admin->setSetting('theme', 'dark');

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/users')
            ->waitFor('#users-grid .rt-wrap')
            ->assertPresent('#users-grid .rt-wrap.rt-dark');
    });
});

test('users grid stays light for the default theme', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/users')
            ->waitFor('#users-grid .rt-wrap')
            ->assertNotPresent('#users-grid .rt-wrap.rt-dark');
    });
});
