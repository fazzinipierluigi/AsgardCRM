<?php

use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('the datagrid_layouts table exists with the expected columns', function () {
    expect(Schema::hasTable('datagrid_layouts'))->toBeTrue();
    expect(Schema::hasColumns('datagrid_layouts', [
        'id', 'user_id', 'page_key', 'name', 'layout_data', 'is_public', 'is_default',
    ]))->toBeTrue();
});

test('every authenticated page exposes a csrf-token meta tag for the layouts scripts to read', function () {
    $response = $this->actingAs(adminUser())->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('<meta name="csrf-token"', false);
});

test('the users grid renders the layouts dropdown and scripts', function () {
    $response = $this->actingAs(adminUser())->get(route('admin.users.index'));

    $response->assertOk();
    $response->assertSee('raccoon-layouts__wrapper', false);
    $response->assertSee('window.RaccoonLayouts', false);
    $response->assertSee('window.wireRaccoonLayouts(grid);', false);
});

test('the roles grid renders the layouts dropdown and marks is_system as a boolean filter', function () {
    $response = $this->actingAs(adminUser())->get(route('admin.roles.index'));

    $response->assertOk();
    $response->assertSee('raccoon-layouts__wrapper', false);
    $response->assertSee("type: 'boolean'", false);
});

test('the translations grid renders the layouts dropdown', function () {
    $response = $this->actingAs(adminUser())->get(route('admin.translations.index'));

    $response->assertOk();
    $response->assertSee('raccoon-layouts__wrapper', false);
});

test('the entities admin grid renders the layouts dropdown', function () {
    $response = $this->actingAs(adminUser())->get(route('admin.entities.index'));

    $response->assertOk();
    $response->assertSee('raccoon-layouts__wrapper', false);
});

test('the entity records grid exposes a filterLookup for a relation column and the layouts dropdown', function () {
    $target = Entity::create(['name' => 'Aziende', 'slug' => 'aziende', 'table_name' => 'entity_aziende']);
    $targetTab = EntityTab::create(['entity_id' => $target->id, 'name' => 'Generale', 'position' => 0]);
    $targetCard = EntityCard::create(['entity_tab_id' => $targetTab->id, 'name' => 'Dati', 'position' => 0]);
    $targetCard->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => 'string', 'position' => 0]);
    app(EntityInstaller::class)->install($target);
    $company = EntityRecord::forEntity($target)->newQuery()->create(['user_id' => adminUser()->id, 'nome' => 'Acme Srl']);

    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create([
        'name' => 'Azienda', 'column_name' => 'azienda', 'type' => 'relation',
        'relation_target_type' => 'entity', 'relation_target' => 'aziende', 'position' => 0,
    ]);
    app(EntityInstaller::class)->install($entity);

    $response = $this->actingAs(adminUser())->get(route('entities.index', $entity));

    $response->assertOk();
    $response->assertSee('raccoon-layouts__wrapper', false);
    $response->assertSee('"filterLookup"', false);
    $response->assertSee('Acme Srl', false);
    $response->assertSee((string) $company->id, false);
});

test('a checkbox field is sent to the client as a raw boolean, not a translated string', function () {
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'VIP', 'column_name' => 'vip', 'type' => 'checkbox', 'position' => 0]);
    app(EntityInstaller::class)->install($entity);

    $admin = adminUser();
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'vip' => true]);

    $response = $this->actingAs($admin)->getJson(route('entities.data', ['entity' => $entity, 'start' => 0, 'limit' => 25]));

    $row = collect($response->json('data'))->first();
    expect($row['vip'])->toBeTrue();
});
