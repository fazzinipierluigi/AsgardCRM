<?php

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityCard;
use App\Models\EntityFieldChange;
use App\Models\EntityRecord;
use App\Models\EntityTab;
use App\Models\User;
use App\Services\EntityInstaller;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function installedEntityWithNameColumn(): Entity
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    return $entity;
}

test('guests are redirected to login', function () {
    $entity = installedEntityWithNameColumn();

    $this->get(route('entities.index', $entity))->assertRedirect(route('login'));
});

test('a non-installed entity 404s', function () {
    $entity = Entity::create(['name' => 'Vuota', 'slug' => 'vuota', 'table_name' => 'entity_vuota']);

    $this->actingAs(adminUser())->get(route('entities.index', $entity))->assertNotFound();
});

test('admin can view an installed entity records page', function () {
    $entity = installedEntityWithNameColumn();

    $this->actingAs(adminUser())->get(route('entities.index', $entity))->assertOk();
});

test('a user without the entity permission is forbidden', function () {
    $entity = installedEntityWithNameColumn();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('entities.index', $entity))->assertForbidden();
});

test('a user with the entity permission can view the records page and list its own records', function () {
    $entity = installedEntityWithNameColumn();
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);
    $permission = Permission::where('key', 'entity_contatti.index')->firstOrFail();
    $role->givePermission($permission);
    $user->assignRole($role);

    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'nome' => 'Mario Rossi']);

    $response = $this->actingAs($user)->getJson(route('entities.data', ['entity' => $entity, 'start' => 0, 'limit' => 25]));

    $response->assertOk()->assertJsonStructure(['data', 'total']);
    expect(collect($response->json('data')))->toHaveCount(1);
});

test('an installed entity shows up in the sidebar menu for a user with permission', function () {
    $entity = installedEntityWithNameColumn();
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);
    $role->givePermission(Permission::where('key', 'entity_contatti.index')->firstOrFail());
    $user->assignRole($role);

    $this->actingAs($user)->get(route('dashboard'))->assertSee('Contatti');
});

test('an installed entity is hidden from the sidebar menu without permission', function () {
    installedEntityWithNameColumn();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Contatti');
});

function installedEntityWithTableColumn(): Entity
{
    $entity = Entity::create(['name' => 'Ordini', 'slug' => 'ordini', 'table_name' => 'entity_ordini']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);
    $card->fields()->create([
        'name' => 'Righe',
        'column_name' => 'righe',
        'type' => EntityFieldType::Table,
        'options' => ['columns' => [
            ['name' => 'quantita', 'label' => 'Quantità', 'type' => 'integer', 'required' => true],
        ]],
        'position' => 1,
    ]);

    app(EntityInstaller::class)->install($entity);

    return $entity->fresh();
}

test('storing a record persists the table field rows as json', function () {
    $entity = installedEntityWithTableColumn();
    $user = adminUser();

    $this->actingAs($user)->post(route('entities.store', $entity), [
        'nome' => 'Ordine 1',
        'righe' => json_encode([['quantita' => 5], ['quantita' => 2]]),
    ])->assertRedirect(route('entities.index', $entity));

    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    expect(json_decode($record->righe, true))->toBe([['quantita' => 5], ['quantita' => 2]]);
});

test('storing a record with a missing required table column is rejected', function () {
    $entity = installedEntityWithTableColumn();
    $user = adminUser();

    $response = $this->actingAs($user)->post(route('entities.store', $entity), [
        'nome' => 'Ordine 1',
        'righe' => json_encode([['quantita' => '']]),
    ]);

    $response->assertSessionHasErrors('righe');
    expect(EntityRecord::forEntity($entity)->count())->toBe(0);
});

test('the records datatable shows a row-count summary for a table field', function () {
    $entity = installedEntityWithTableColumn();
    $user = adminUser();
    EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $user->id,
        'nome' => 'Ordine 1',
        'righe' => json_encode([['quantita' => 1], ['quantita' => 2], ['quantita' => 3]]),
    ]);

    $response = $this->actingAs($user)->getJson(route('entities.data', ['entity' => $entity, 'start' => 0, 'limit' => 25]));

    expect(collect($response->json('data'))->first()['righe'])->toBe('3 righe');
});

test('a button field is excluded from record persistence and from the records datatable', function () {
    $entity = installedEntityWithNameColumn();
    $card = $entity->allFields()->first()->card;
    $card->fields()->create([
        'name' => 'Avvia',
        'column_name' => 'avvia',
        'type' => EntityFieldType::Button,
        'options' => ['button_action' => 'javascript', 'button_workflow_id' => null, 'button_importer_ids' => [], 'button_javascript' => 'noop()'],
        'position' => 1,
    ]);
    $user = adminUser();

    $this->actingAs($user)->post(route('entities.store', $entity), [
        'nome' => 'Mario Rossi',
        'avvia' => 'ignored',
    ])->assertRedirect(route('entities.index', $entity));

    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    expect($record->getAttributes())->not->toHaveKey('avvia');

    $response = $this->actingAs($user)->getJson(route('entities.data', ['entity' => $entity, 'start' => 0, 'limit' => 25]));
    expect(collect($response->json('data'))->first())->not->toHaveKey('avvia');
});

test('storing a record logs its initial field values as one transaction', function () {
    $entity = installedEntityWithNameColumn();
    $user = adminUser();

    $this->actingAs($user)->post(route('entities.store', $entity), ['nome' => 'Mario Rossi']);

    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    $changes = EntityFieldChange::where('entity_slug', 'contatti')->where('entity_id', $record->id)->get();

    expect($changes)->toHaveCount(1);
    expect($changes->first()->old_value)->toBeNull();
    expect($changes->first()->new_value)->toBe('Mario Rossi');
    expect($changes->first()->changed_by_user_id)->toBe($user->id);
});

test('updating a record logs only the changed field, grouped under one transaction', function () {
    $entity = installedEntityWithNameColumn();
    $user = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'nome' => 'Mario Rossi']);

    $this->actingAs($user)->put(route('entities.update', [$entity, $record]), ['nome' => 'Luigi Bianchi']);

    $changes = EntityFieldChange::where('entity_id', $record->id)->get();

    expect($changes)->toHaveCount(1);
    expect($changes->first()->old_value)->toBe('Mario Rossi');
    expect($changes->first()->new_value)->toBe('Luigi Bianchi');
});

test('the edit page shows the record\'s change history', function () {
    $entity = installedEntityWithNameColumn();
    $user = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'nome' => 'Mario Rossi']);
    $this->actingAs($user)->put(route('entities.update', [$entity, $record]), ['nome' => 'Luigi Bianchi']);

    $response = $this->actingAs($user)->get(route('entities.edit', [$entity, $record]));

    $response->assertOk()
        ->assertSee(t('Storico modifiche'))
        ->assertSee('Mario Rossi')
        ->assertSee('Luigi Bianchi');
});

test('an entity icon renders as inline svg in the sidebar menu, not a webfont class', function () {
    $entity = installedEntityWithNameColumn();
    $entity->update(['icon' => 'building']);
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);
    $role->givePermission(Permission::where('key', 'entity_contatti.index')->firstOrFail());
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSee(icon('building'), false);
    $response->assertDontSee('<i class="building">', false);
});
