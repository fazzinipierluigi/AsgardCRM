<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Laravel\Dusk\Browser;

test('toggling a field live shows/hides, locks and requires other fields per the entity conditional rules', function () {
    $entity = Entity::create(['name' => 'Clienti', 'slug' => 'dusk-cond-clienti', 'table_name' => 'entity_dusk_cond_clienti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Tipo azienda', 'column_name' => 'tipo_azienda', 'type' => EntityFieldType::Checkbox, 'position' => 0]);
    $ivaField = $card->fields()->create(['name' => 'Partita IVA', 'column_name' => 'partita_iva', 'type' => EntityFieldType::String, 'position' => 1]);
    $noteField = $card->fields()->create(['name' => 'Note interne', 'column_name' => 'note_interne', 'type' => EntityFieldType::String, 'position' => 2]);
    app(EntityInstaller::class)->install($entity);
    $entity = $entity->fresh();

    $hideCondition = $entity->fieldConditions()->create(['name' => 'Nascondi P.IVA', 'rule' => ['==' => [['var' => 'tipo_azienda'], '1']], 'position' => 0]);
    $hideCondition->targets()->create(['entity_field_id' => $ivaField->id, 'visible' => false, 'readonly' => false, 'required' => false]);

    $lockCondition = $entity->fieldConditions()->create(['name' => 'Blocca note', 'rule' => ['==' => [['var' => 'tipo_azienda'], '1']], 'position' => 1]);
    $lockCondition->targets()->create(['entity_field_id' => $noteField->id, 'visible' => true, 'readonly' => true, 'required' => true]);

    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/entities/dusk-cond-clienti/create')
            ->waitFor('[data-column="partita_iva"]')
            ->assertVisible('[data-column="partita_iva"]')
            ->assertMissing('[data-column="note_interne"] [data-required-marker]:not(.d-none)')
            ->check('tipo_azienda')
            ->waitUntil('document.querySelector("[data-column=\"partita_iva\"]").classList.contains("d-none")')
            ->assertVisible('[data-column="note_interne"] [data-required-marker]:not(.d-none)');

        $readonly = $browser->script('return document.querySelector("[data-column=\"note_interne\"] input[name=\"note_interne\"]").readOnly;')[0];
        expect($readonly)->toBeTrue();

        $browser->uncheck('tipo_azienda')
            ->waitUntil('!document.querySelector("[data-column=\"partita_iva\"]").classList.contains("d-none")')
            ->assertMissing('[data-column="note_interne"] [data-required-marker]:not(.d-none)');
    });
});
