<?php

use Fazzinipierluigi\CrmCore\Database\Seeders\TranslationSeeder;
use Laravel\Dusk\Browser;

test('the admin sidebar is translated according to the user language preference', function () {
    $admin = adminUser();
    $this->seed(TranslationSeeder::class);

    $admin->setSetting('language', 'en');

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/users')
            ->assertSeeIn('[data-testid="menu-users"]', 'Users')
            ->assertSeeIn('[data-testid="menu-roles"]', 'Roles')
            ->assertSeeIn('[data-testid="menu-translations"]', 'Translations')
            ->assertDontSeeIn('[data-testid="menu-users"]', 'Utenti');
    });
});
