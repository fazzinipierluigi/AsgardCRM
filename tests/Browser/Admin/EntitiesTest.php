<?php

use Fazzinipierluigi\CrmCore\Models\Entity;
use Laravel\Dusk\Browser;

test('admin can create an entity and design its tab/card/field structure', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/entities/create')
            ->type('name', 'Contatti')
            ->press('Crea entità')
            ->waitForLocation('/admin/entities/1/builder');

        $browser->press('Aggiungi tab')
            ->waitFor('[data-testid="name-modal-input"]')
            ->type('[data-testid="name-modal-input"]', 'Generale')
            ->click('[data-testid="name-modal-save"]')
            ->waitUntilMissing('.modal-backdrop');

        $browser->click('[data-testid="add-card-btn"]')
            ->waitFor('[data-testid="name-modal-input"]')
            ->type('[data-testid="name-modal-input"]', 'Anagrafica')
            ->click('[data-testid="name-modal-save"]')
            ->waitUntilMissing('.modal-backdrop');

        $browser->click('[data-testid="add-field-btn"]')
            ->waitFor('[data-testid="field-modal-name"]')
            ->type('[data-testid="field-modal-name"]', 'Nome')
            ->type('[data-testid="field-modal-column"]', 'nome')
            ->click('[data-testid="field-modal-save"]')
            ->waitUntilMissing('.modal-backdrop');

        $browser->press('Salva struttura')
            ->waitForLocation('/admin/entities/1/builder')
            ->waitForText('Struttura salvata correttamente.');
    });

    $entity = Entity::where('slug', 'contatti')->firstOrFail();
    $entity->load('tabs.cards.fields');

    expect($entity->tabs)->toHaveCount(1);
    expect($entity->tabs->first()->name)->toBe('Generale');
    expect($entity->tabs->first()->cards->first()->name)->toBe('Anagrafica');
    expect($entity->allFields()->first()->column_name)->toBe('nome');
});

test('picking an icon in the create form persists on save', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/entities/create')
            ->assertVisible('[data-testid="entity-icon-select"]')
            ->select('[data-testid="entity-icon-select"]', 'building')
            ->type('name', 'Aziende')
            ->press('Crea entità')
            ->waitForLocation('/admin/entities/1/builder');
    });

    expect(Entity::where('slug', 'aziende')->firstOrFail()->icon)->toBe('building');
});

test('the icon field is a real tom select, lists every icon and previews it inline via genuine click interaction', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/entities/create')
            ->assertPresent('#icon ~ .ts-wrapper')
            ->click('#icon ~ .ts-wrapper .ts-control')
            ->waitFor('.ts-dropdown .option');

        // Not capped at Tom Select's default maxOptions of 50 — every real
        // icon (icon_names()) must be reachable, not just the first 50.
        $optionCount = $browser->script('return document.querySelectorAll(".ts-dropdown .option").length;')[0];
        expect($optionCount)->toBeGreaterThan(50);

        $browser->type('#icon ~ .ts-wrapper .ts-control input', 'building')
            ->waitFor('.ts-dropdown .option[data-value="building"]')
            ->assertPresent('.ts-dropdown .option[data-value="building"] img')
            ->click('.ts-dropdown .option[data-value="building"]')
            ->waitFor('#icon ~ .ts-wrapper .item img')
            ->assertSee('building');

        $itemImgSrc = $browser->script("return document.querySelector('#icon ~ .ts-wrapper .item img').getAttribute('src');")[0];
        expect($itemImgSrc)->toContain('/tabler-icons/outline/building');

        // The dropdown briefly rendered ~5,100 option nodes (maxOptions:
        // null, see tom-select.js) — give the browser a beat to settle
        // before the next test navigates, so a slow render doesn't blow
        // past the next test's own wait timeouts.
        $browser->visit('/dashboard')->waitForLocation('/dashboard');
    });
});

test('the entity form lays out name at 8 columns and icon at 4 columns', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)->visit('/admin/entities/create')->waitFor('#icon');

        $nameColClass = $browser->script('return document.getElementById("name").closest(".row > div").className;')[0];
        $iconColClass = $browser->script('return document.getElementById("icon").closest(".row > div").className;')[0];

        expect($nameColClass)->toContain('col-md-8');
        expect($iconColClass)->toContain('col-md-4');
    });
});
