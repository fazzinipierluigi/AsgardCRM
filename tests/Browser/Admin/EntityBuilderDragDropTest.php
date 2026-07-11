<?php

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityCard;
use App\Models\EntityTab;
use Laravel\Dusk\Browser;

test('reordering fields in the DOM and resizing one persists on save', function () {
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);
    $card->fields()->create(['name' => 'Cognome', 'column_name' => 'cognome', 'type' => EntityFieldType::String, 'position' => 1]);

    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin, $entity) {
        $browser->loginAs($admin)
            ->visit("/admin/entities/{$entity->id}/builder")
            ->waitFor('.field-item');

        // Headless Chrome/Selenium can't reliably simulate a native HTML5
        // drag gesture, so exercise the same DOM outcome a real drag would
        // produce: swap the two field-item nodes' order, and resize the
        // first one via the resize handler the builder exposes on window
        // specifically for this (see resources/js/entity-builder.js).
        $browser->script([
            'var container = document.querySelector(".fields-container");'.
            'var items = container.querySelectorAll(".field-item");'.
            'container.insertBefore(items[1], items[0]);'.
            'window.__entityBuilderSetFieldWidth(container.querySelectorAll(".field-item")[0], 6);',
        ]);

        $browser->press('Salva struttura')
            ->waitForText('Struttura salvata correttamente.');
    });

    $entity->load('tabs.cards.fields');
    $fields = $entity->tabs->first()->cards->first()->fields;

    expect($fields->pluck('column_name')->all())->toBe(['cognome', 'nome']);
    expect($fields->firstWhere('column_name', 'cognome')->width)->toBe(6);
    expect($fields->firstWhere('column_name', 'nome')->width)->toBe(12);
});

test('dragging the resize handle with real mouse events narrows a field', function () {
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin, $entity) {
        $browser->loginAs($admin)
            ->visit("/admin/entities/{$entity->id}/builder")
            ->waitFor('.field-resize-handle');

        // Exercise the actual mousedown/mousemove/mouseup listeners wired
        // in entity-builder.js — not the window.__entityBuilderSetFieldWidth
        // test-only helper used by the test above — to prove the real drag
        // gesture (as a user would perform it) narrows the field.
        $browser->script(
            'var handle = document.querySelector(".field-resize-handle");'.
            'var rect = handle.getBoundingClientRect();'.
            'var startX = rect.left + rect.width / 2;'.
            'var startY = rect.top + rect.height / 2;'.
            'function fire(el, type, x, y) {'.
            '    el.dispatchEvent(new MouseEvent(type, {bubbles: true, cancelable: true, clientX: x, clientY: y, button: 0}));'.
            '}'.
            'fire(handle, "mousedown", startX, startY);'.
            'fire(document, "mousemove", startX - 250, startY);'.
            'fire(document, "mouseup", startX - 250, startY);'
        );

        $browser->pause(100);

        $width = $browser->script('return document.querySelector(".field-item").dataset.width;')[0];
        expect((int) $width)->toBeLessThan(12);

        // Releasing the resize handle must NOT also pop the field modal
        // open (mouseup lands inside .field-preview, which used to be
        // enough to trigger the single-click "edit field" handler).
        $browser->assertMissing('[data-testid="field-modal"].show');
    });
});

test('a single click on a field preview does not open its modal, a double-click does', function () {
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin, $entity) {
        $browser->loginAs($admin)
            ->visit("/admin/entities/{$entity->id}/builder")
            ->waitFor('[data-testid="field-preview"]');

        $browser->click('[data-testid="field-preview"]')
            ->pause(150)
            ->assertMissing('[data-testid="field-modal"].show');

        $browser->script('document.querySelector("[data-testid=\"field-preview\"]").dispatchEvent(new MouseEvent("dblclick", {bubbles: true}));');

        $browser->waitFor('[data-testid="field-modal"].show')
            ->assertInputValue('[data-testid="field-modal-name"]', 'Nome');
    });
});
