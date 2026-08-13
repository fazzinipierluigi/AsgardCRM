<?php

use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Models\EntityRelation;
use Laravel\Dusk\Browser;

test('a user can attach and detach a related record through the relations sidebar and sheet', function () {
    $clienti = relationTestEntity('dusk-rel-clienti', 'Clienti');
    $prodotti = relationTestEntity('dusk-rel-prodotti', 'Prodotti');
    $relation = EntityRelation::create(['entity_a_id' => $clienti->id, 'entity_b_id' => $prodotti->id, 'name' => 'Acquisti']);

    $admin = adminUser();
    $clienteRecord = EntityRecord::forEntity($clienti)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Mario Rossi']);
    $prodottoRecord = EntityRecord::forEntity($prodotti)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Sedia da ufficio']);

    $this->browse(function (Browser $browser) use ($admin, $clienteRecord, $prodottoRecord) {
        $browser->loginAs($admin)
            ->visit("/entities/dusk-rel-clienti/{$clienteRecord->id}/edit")
            ->waitFor('[data-testid="entity-relations-card"]')
            ->assertSeeIn('[data-testid="entity-relations-card"]', 'Acquisti')
            ->assertSeeIn('[data-entity-relation-count]', '0')
            ->click('[data-entity-relation-open]')
            ->waitUntil('document.getElementById("entity-relations-offcanvas").classList.contains("show")')
            ->assertSeeIn('#entity-relations-offcanvas-title', 'Acquisti')
            ->click('#entity-relations-attach-select ~ .ts-wrapper .ts-control input')
            ->type('#entity-relations-attach-select ~ .ts-wrapper .ts-control input', 'Sedia')
            ->waitFor('.ts-dropdown .option[data-value="'.$prodottoRecord->id.'"]', 5)
            ->click('.ts-dropdown .option[data-value="'.$prodottoRecord->id.'"]')
            ->click('#entity-relations-attach-btn')
            ->waitForTextIn('[data-testid="entity-relations-table"]', 'Sedia da ufficio')
            ->waitUntil('document.querySelector("[data-entity-relation-count]").textContent === "1"');

        $browser->click('[data-testid="entity-relations-table"] [data-testid="entity-relation-detach-btn"]')
            ->waitUntil('document.querySelector("[data-testid=\"entity-relations-table\"]").textContent.indexOf("Sedia da ufficio") === -1')
            ->waitUntil('document.querySelector("[data-entity-relation-count]").textContent === "0"');
    });
});
