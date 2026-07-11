<?php

use App\Models\Translation;
use Laravel\Dusk\Browser;

test('admin can create, edit and delete a translation', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/translations/create')
            ->type('key', 'dashboard.welcome')
            ->select('language', 'it')
            ->type('value', 'Benvenuto')
            ->press('Crea traduzione')
            ->waitForLocation('/admin/translations')
            ->waitForText('dashboard.welcome');

        $browser->clickLink('Modifica')
            ->waitForText('Modifica traduzione')
            ->assertInputValue('key', 'dashboard.welcome')
            ->type('value', 'Ciao')
            ->press('Salva modifiche')
            ->waitForLocation('/admin/translations')
            ->waitForText('Traduzione aggiornata correttamente.');

        $browser->press('Elimina')
            ->waitForLocation('/admin/translations')
            ->waitForText('Traduzione eliminata correttamente.');
    });

    expect(Translation::where('key', 'dashboard.welcome')->exists())->toBeFalse();
});
