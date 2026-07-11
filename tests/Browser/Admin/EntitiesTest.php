<?php

use App\Models\Entity;
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
