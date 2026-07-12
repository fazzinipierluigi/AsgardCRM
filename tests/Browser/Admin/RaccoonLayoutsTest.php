<?php

use Fazzinipierluigi\LaraccoonLayouts\Models\DatagridLayout;
use Laravel\Dusk\Browser;

test('admin can save the users grid as a named layout', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/users')
            ->waitFor('#raccoon-layouts-menu-trigger')
            ->click('#raccoon-layouts-menu-trigger')
            ->waitFor('#raccoon-layouts-item-save-as')
            ->click('#raccoon-layouts-item-save-as')
            ->waitForDialog()
            ->typeInDialog('La mia vista')
            ->acceptDialog();

        $browser->pause(300);
    });

    $layout = DatagridLayout::where('user_id', $admin->id)->where('name', 'La mia vista')->first();

    expect($layout)->not->toBeNull();
    expect($layout->layout_data)->toHaveKey('columns');
});

test('the layout select is tom select wrapped and reflects a newly saved layout', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/users')
            ->waitFor('#raccoon-layouts-select')
            ->assertPresent('#raccoon-layouts-select ~ .ts-wrapper');

        $browser->click('#raccoon-layouts-menu-trigger')
            ->waitFor('#raccoon-layouts-item-save-as')
            ->click('#raccoon-layouts-item-save-as')
            ->waitForDialog()
            ->typeInDialog('Tom Select View')
            ->acceptDialog()
            ->pause(300);

        // buildOptions() rebuilds with keepSelection=false after "save as" (vendor
        // behavior, unrelated to the Tom Select conversion), so the new layout
        // isn't auto-selected — assert it exists as an option instead.
        $hasNewOption = $browser->script("var ts = document.getElementById('raccoon-layouts-select').tomselect; return Object.values(ts.options).some(function (o) { return o.text === 'Tom Select View'; });")[0];
        expect($hasNewOption)->toBeTrue();
    });
});
