<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function entityReadyToInstall(): Entity
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    return $entity;
}

test('admin can install an entity from the index page', function () {
    $admin = adminUser();
    $entity = entityReadyToInstall();

    $response = $this->actingAs($admin)->post(route('admin.entities.install', $entity));

    $response->assertRedirect();
    expect($entity->fresh()->is_installed)->toBeTrue();
    expect(Schema::hasTable('entity_contatti'))->toBeTrue();
});

test('installing an incomplete entity redirects back with an error', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Vuota', 'slug' => 'vuota', 'table_name' => 'entity_vuota']);

    $response = $this->actingAs($admin)->post(route('admin.entities.install', $entity));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($entity->fresh()->is_installed)->toBeFalse();
});

test('admin can uninstall a custom installed entity', function () {
    $admin = adminUser();
    $entity = entityReadyToInstall();
    $this->actingAs($admin)->post(route('admin.entities.install', $entity));

    $response = $this->actingAs($admin)->post(route('admin.entities.uninstall', $entity));

    $response->assertRedirect();
    expect($entity->fresh()->is_installed)->toBeFalse();
    expect(Schema::hasTable('entity_contatti'))->toBeFalse();
});

test('a system entity cannot be uninstalled via the route', function () {
    $admin = adminUser();
    $entity = entityReadyToInstall();
    $entity->update(['is_system' => true]);
    $this->actingAs($admin)->post(route('admin.entities.install', $entity));

    $response = $this->actingAs($admin)->post(route('admin.entities.uninstall', $entity));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($entity->fresh()->is_installed)->toBeTrue();
});

test('the builder page for an installed entity still renders, locked', function () {
    $admin = adminUser();
    $entity = entityReadyToInstall();
    $this->actingAs($admin)->post(route('admin.entities.install', $entity));

    $response = $this->actingAs($admin)->get(route('admin.entities.builder.edit', $entity));

    $response->assertOk();
    $response->assertSee('entity-builder-installed-notice', false);
});
