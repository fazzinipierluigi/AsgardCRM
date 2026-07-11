<?php

use App\Models\User;
use Laravel\Dusk\Browser;

test('user can change preferences and the theme is applied', function () {
    seedLanguages();
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/settings')
            ->select('theme', 'dark')
            ->select('language', 'en')
            ->press('Salva preferenze')
            ->waitForText('Preferenze aggiornate.');

        $theme = $browser->script('return document.documentElement.getAttribute("data-bs-theme");')[0];
        expect($theme)->toBe('dark');
    });

    expect($user->getSetting('theme'))->toBe('dark');
    expect($user->getSetting('language'))->toBe('en');
});
