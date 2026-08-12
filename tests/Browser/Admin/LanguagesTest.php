<?php

use Fazzinipierluigi\CrmCore\Models\Language;
use Fazzinipierluigi\CrmCore\Models\Translation;
use Laravel\Dusk\Browser;

test('admin can add a new language and use it right away in a translation', function () {
    seedLanguages();
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/languages')
            ->type('code', 'fr')
            ->type('name', 'Français')
            ->press('Aggiungi lingua')
            ->waitForLocation('/admin/languages')
            ->waitForText('Lingua creata correttamente.')
            ->assertSee('fr');
    });

    expect(Language::where('code', 'fr')->exists())->toBeTrue();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/translations/create')
            ->assertPresent('textarea[name="values[fr]"]');
    });
});

test('a language with existing translations cannot be deleted from the UI', function () {
    seedLanguages();
    $admin = adminUser();
    Translation::create(['key' => 'dashboard.welcome', 'language' => 'it', 'value' => 'Benvenuto']);

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/languages')
            ->press('[data-testid="language-destroy-it"]')
            ->waitForDialog()
            ->acceptDialog()
            ->waitForText('Non è possibile eliminare una lingua che ha ancora traduzioni associate.');
    });

    expect(Language::where('code', 'it')->exists())->toBeTrue();
});
