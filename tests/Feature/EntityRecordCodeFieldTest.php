<?php

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityCard;
use App\Models\EntityRecord;
use App\Models\EntityTab;
use App\Models\User;
use App\Services\EntityInstaller;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function invoicesEntityWithCodeField(): Entity
{
    $entity = Entity::create(['name' => 'Fatture', 'slug' => 'fatture', 'table_name' => 'entity_fatture']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Codice', 'column_name' => 'codice', 'type' => EntityFieldType::Code, 'options' => ['prefix' => 'INV-'], 'position' => 0]);
    $card->fields()->create(['name' => 'Descrizione', 'column_name' => 'descrizione', 'type' => EntityFieldType::String, 'position' => 1]);

    app(EntityInstaller::class)->install($entity);

    return $entity;
}

function userWithFullEntityAccess(Entity $entity): User
{
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore '.uniqid(), 'slug' => 'operatore-'.uniqid()]);

    foreach (['index', 'create', 'edit'] as $action) {
        $role->givePermission(Permission::where('key', "entity_{$entity->slug}.{$action}")->firstOrFail());
    }

    $user->assignRole($role);

    return $user;
}

test('creating a record generates the code field automatically', function () {
    $entity = invoicesEntityWithCodeField();
    $user = userWithFullEntityAccess($entity);

    $this->actingAs($user)->post(route('entities.store', $entity), ['descrizione' => 'Prima fattura']);

    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    expect($record->codice)->toBe('INV-1');
});

test('the code increments across records', function () {
    $entity = invoicesEntityWithCodeField();
    $user = userWithFullEntityAccess($entity);

    $this->actingAs($user)->post(route('entities.store', $entity), ['descrizione' => 'Uno']);
    $this->actingAs($user)->post(route('entities.store', $entity), ['descrizione' => 'Due']);

    $codes = EntityRecord::forEntity($entity)->newQuery()->orderBy('id')->pluck('codice');
    expect($codes->all())->toBe(['INV-1', 'INV-2']);
});

test('submitting a code value from the client is ignored', function () {
    $entity = invoicesEntityWithCodeField();
    $user = userWithFullEntityAccess($entity);

    $this->actingAs($user)->post(route('entities.store', $entity), ['descrizione' => 'Test', 'codice' => 'HACKED']);

    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    expect($record->codice)->toBe('INV-1');
});

test('updating a record never changes its already-generated code', function () {
    $entity = invoicesEntityWithCodeField();
    $user = userWithFullEntityAccess($entity);

    $this->actingAs($user)->post(route('entities.store', $entity), ['descrizione' => 'Originale']);
    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();

    $this->actingAs($user)->put(route('entities.update', [$entity, $record->id]), ['descrizione' => 'Modificata', 'codice' => 'IGNORED']);

    expect($record->fresh()->codice)->toBe('INV-1');
    expect($record->fresh()->descrizione)->toBe('Modificata');
});
