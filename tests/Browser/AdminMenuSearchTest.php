<?php

use Laravel\Dusk\Browser;

test('sidebar search filters menu items and hides empty section titles', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/users')
            ->waitFor('#sidebar-menu-search')
            ->type('#sidebar-menu-search', 'lingu')
            ->pause(100);

        $hidden = fn (string $testid) => $browser->script(
            "return document.querySelector('[data-testid=\"{$testid}\"]').closest('li').classList.contains('d-none');"
        )[0];

        expect($hidden('menu-languages'))->toBeFalse();
        expect($hidden('menu-users'))->toBeTrue();
        expect($hidden('menu-entities'))->toBeTrue();

        $browser->keys('#sidebar-menu-search', ['{control}', 'a'], '{backspace}')
            ->pause(100);

        expect($hidden('menu-users'))->toBeFalse();
        expect($hidden('menu-entities'))->toBeFalse();
    });
});
