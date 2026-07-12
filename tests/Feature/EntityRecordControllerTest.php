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
