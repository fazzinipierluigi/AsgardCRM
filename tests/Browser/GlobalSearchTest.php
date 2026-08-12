<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Laravel\Dusk\Browser;

function installedGlobalSearchEntity(): Entity
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);
    app(EntityInstaller::class)->install($entity);

    return $entity;
}

test('typing in the navbar search opens a dropdown and navigates to the matching record', function () {
    $entity = installedGlobalSearchEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Mario Rossi']);

    $this->browse(function (Browser $browser) use ($admin, $entity, $record) {
        $browser->loginAs($admin)
            ->visit('/dashboard')
            ->waitFor('#global-search-input')
            ->type('#global-search-input', 'Mario')
            ->waitFor('#global-search-results.show')
            ->waitForText('Mario Rossi')
            ->assertSee('Contatti')
            ->clickLink('Mario Rossi')
            ->waitForLocation("/entities/{$entity->slug}/{$record->id}/edit");
    });
});

test('the navbar search is not present in the admin section', function () {
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/users')
            ->assertMissing('#global-search-input');
    });
});
