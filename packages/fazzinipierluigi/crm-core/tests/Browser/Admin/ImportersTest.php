<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Models\Importer;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Laravel\Dusk\Browser;

function duskImporterEntity(): Entity
{
    $entity = Entity::create(['name' => 'Contatti Dusk', 'slug' => 'contatti-dusk-'.uniqid(), 'table_name' => 'entity_contatti_dusk_'.uniqid()]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'required' => true, 'position' => 0]);
    $card->fields()->create(['name' => 'Email', 'column_name' => 'email', 'type' => EntityFieldType::String, 'position' => 1]);

    app(EntityInstaller::class)->install($entity);

    return $entity;
}

test('admin can create a csv importer through the wizard', function () {
    $admin = adminUser();
    $entity = duskImporterEntity();

    $csvPath = tempnam(sys_get_temp_dir(), 'importer_dusk_csv_');
    file_put_contents($csvPath, "nome,email\nMario Rossi,mario@example.com\n");

    $this->browse(function (Browser $browser) use ($admin, $entity, $csvPath) {
        $browser->loginAs($admin)
            ->visit('/admin/importers/create')
            ->type('title', 'Import Dusk CSV')
            ->type('description', 'Importatore creato dal test Dusk');

        $browser->script([
            "window.setSelectValue('#entity_id', '{$entity->id}');",
            "window.setSelectValue('#channel', 'csv', false);",
        ]);

        $browser->press('Avanti')
            ->press('Avanti')
            ->type('#path_or_url_csv', $csvPath)
            ->press('Avanti')
            ->waitUntil("document.querySelectorAll('#importer-mapping-body tr').length === 2", 10);

        $browser->script([
            "document.querySelector('[data-source-field=\"nome\"] .mapping-target').tomselect.setValue('nome', false);",
            "document.querySelector('[data-source-field=\"email\"] .mapping-target').tomselect.setValue('email', false);",
            "document.querySelector('[data-source-field=\"nome\"] .unique-key-radio').click();",
        ]);

        $browser->press('Avanti')
            ->press('Crea importatore')
            ->waitForLocation('/admin/importers')
            ->waitForText('Importatore creato correttamente.')
            ->assertSee('Import Dusk CSV');
    });

    $importer = Importer::where('title', 'Import Dusk CSV')->firstOrFail();
    expect($importer->entity_id)->toBe($entity->id);
    expect($importer->channel->value)->toBe('csv');
    expect($importer->config['path_or_url'])->toBe($csvPath);
    expect($importer->field_mapping)->toBe(['nome' => 'nome', 'email' => 'email']);
    expect($importer->unique_key_field)->toBe('nome');
    expect($importer->schedule_type->value)->toBe('manual');

    unlink($csvPath);
});
