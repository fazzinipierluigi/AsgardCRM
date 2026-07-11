<?php

use App\Models\Translation;
use Laravel\Dusk\Browser;

test('admin can create, edit and delete a translation', function () {
    seedLanguages();
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/translations/create')
            ->type('key', 'dashboard.welcome')
            ->type('values[it]', 'Benvenuto')
            ->type('values[en]', 'Welcome')
            ->press('Crea traduzione')
            ->waitForLocation('/admin/translations')
            ->waitForText('dashboard.welcome');

        $browser->clickLink('Modifica')
            ->waitForText('Modifica traduzione')
            ->assertInputValue('key', 'dashboard.welcome')
            ->type('values[it]', 'Ciao')
            ->press('Salva modifiche')
            ->waitForLocation('/admin/translations')
            ->waitForText('Traduzione aggiornata correttamente.');

        $browser->press('Elimina')
            ->waitForLocation('/admin/translations')
            ->waitForText('Traduzione eliminata correttamente.');
    });

    expect(Translation::where('key', 'dashboard.welcome')->exists())->toBeFalse();
});
