<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Laravel\Dusk\Browser;

test('a user can create and then edit a record through the generic entity form', function () {
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'required' => true, 'position' => 0]);
    app(EntityInstaller::class)->install($entity);

    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/entities/contatti/create')
            ->type('nome', 'Mario Rossi')
            ->press('Salva')
            ->waitForLocation('/entities/contatti')
            ->waitForText('Mario Rossi');
    });

    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    expect($record->nome)->toBe('Mario Rossi');

    $this->browse(function (Browser $browser) use ($admin, $record) {
        $browser->loginAs($admin)
            ->visit("/entities/contatti/{$record->id}/edit")
            ->type('nome', 'Mario Verdi')
            ->press('Salva modifiche')
            ->waitForLocation('/entities/contatti')
            ->waitForText('Mario Verdi');
    });

    expect($record->fresh()->nome)->toBe('Mario Verdi');
});
