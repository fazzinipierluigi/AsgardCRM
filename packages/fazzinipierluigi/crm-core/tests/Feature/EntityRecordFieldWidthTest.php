<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the record form renders each field wrapped in its configured column width', function () {
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0, 'width' => 6]);
    $card->fields()->create(['name' => 'Cognome', 'column_name' => 'cognome', 'type' => EntityFieldType::String, 'position' => 1, 'width' => 4]);

    app(EntityInstaller::class)->install($entity);

    $admin = adminUser();

    $response = $this->actingAs($admin)->get(route('entities.create', $entity));

    $response->assertOk();
    $response->assertSee('col-md-6', false);
    $response->assertSee('col-md-4', false);
});

test('the record form always renders cards at full width', function () {
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    $admin = adminUser();

    $response = $this->actingAs($admin)->get(route('entities.create', $entity));

    $response->assertOk();
    $response->assertDontSee('col-md-6"', false);
});

test('the record form submit button lives in the page buttons area, outside the form', function () {
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    $admin = adminUser();

    $response = $this->actingAs($admin)->get(route('entities.create', $entity));

    $response->assertOk();
    $response->assertSee('form="entity-record-form"', false);
});
