<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function installableEntity(): Entity
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);
    $card->fields()->create(['name' => 'Note', 'column_name' => 'note', 'type' => EntityFieldType::Textarea, 'position' => 1]);

    return $entity;
}

test('installing an entity creates its table with a column per field', function () {
    $entity = installableEntity();

    app(EntityInstaller::class)->install($entity);

    expect(Schema::hasTable('entity_contatti'))->toBeTrue();
    expect(Schema::hasColumns('entity_contatti', ['id', 'user_id', 'nome', 'note', 'created_at', 'updated_at']))->toBeTrue();
    expect($entity->fresh()->is_installed)->toBeTrue();
});

test('installing an entity creates its four CRUD permissions', function () {
    $entity = installableEntity();

    app(EntityInstaller::class)->install($entity);

    expect(Permission::where('key', 'entity_contatti.index')->exists())->toBeTrue();
    expect(Permission::where('key', 'entity_contatti.create')->exists())->toBeTrue();
    expect(Permission::where('key', 'entity_contatti.edit')->exists())->toBeTrue();
    expect(Permission::where('key', 'entity_contatti.delete')->exists())->toBeTrue();
});

test('an entity without any tab cannot be installed', function () {
    $entity = Entity::create(['name' => 'Vuota', 'slug' => 'vuota', 'table_name' => 'entity_vuota']);

    expect(fn () => app(EntityInstaller::class)->install($entity))->toThrow(RuntimeException::class);
    expect(Schema::hasTable('entity_vuota'))->toBeFalse();
    expect($entity->fresh()->is_installed)->toBeFalse();
});

test('an entity with a tab but no card cannot be installed', function () {
    $entity = Entity::create(['name' => 'Vuota', 'slug' => 'vuota', 'table_name' => 'entity_vuota']);
    EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);

    expect(fn () => app(EntityInstaller::class)->install($entity))->toThrow(RuntimeException::class);
});

test('a relation field gets a foreign key to the target entity table when both are installed', function () {
    $target = installableEntity();
    app(EntityInstaller::class)->install($target);

    $entity = Entity::create(['name' => 'Ordini', 'slug' => 'ordini', 'table_name' => 'entity_ordini']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create([
        'name' => 'Contatto',
        'column_name' => 'contatto',
        'type' => EntityFieldType::Relation,
        'relation_target_type' => 'entity',
        'relation_target' => 'contatti',
        'position' => 0,
    ]);

    app(EntityInstaller::class)->install($entity);

    expect(Schema::hasColumn('entity_ordini', 'contatto_id'))->toBeTrue();
});

test('a calendar entity gets polymorphic relatable columns on install', function () {
    $entity = Entity::create(['name' => 'Calendario', 'slug' => 'calendario', 'table_name' => 'entity_calendario', 'is_calendar' => true]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dettagli', 'position' => 0]);
    $card->fields()->create(['name' => 'Titolo', 'column_name' => 'title', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    expect(Schema::hasColumns('entity_calendario', ['relatable_type', 'relatable_id']))->toBeTrue();
});

test('a non-calendar entity does not get relatable columns', function () {
    $entity = installableEntity();

    app(EntityInstaller::class)->install($entity);

    expect(Schema::hasColumn('entity_contatti', 'relatable_type'))->toBeFalse();
    expect(Schema::hasColumn('entity_contatti', 'relatable_id'))->toBeFalse();
});

test('uninstalling an entity drops its table and removes its permissions', function () {
    $entity = installableEntity();
    app(EntityInstaller::class)->install($entity);

    app(EntityInstaller::class)->uninstall($entity);

    expect(Schema::hasTable('entity_contatti'))->toBeFalse();
    expect(Permission::where('key', 'entity_contatti.index')->exists())->toBeFalse();
    expect($entity->fresh()->is_installed)->toBeFalse();
});

test('a system entity cannot be uninstalled', function () {
    $entity = installableEntity();
    $entity->update(['is_system' => true]);
    app(EntityInstaller::class)->install($entity);

    expect(fn () => app(EntityInstaller::class)->uninstall($entity))->toThrow(RuntimeException::class);
    expect(Schema::hasTable('entity_contatti'))->toBeTrue();
});
