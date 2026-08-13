<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Enums\EntityVisibilityLevel;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Models\EntityRoleVisibility;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function installedSearchableEntity(string $slug = 'contatti', string $name = 'Contatti'): Entity
{
    $entity = Entity::create(['name' => $name, 'slug' => $slug, 'table_name' => "entity_{$slug}"]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    return $entity;
}

function userWithEntityPermission(Entity $entity, ?EntityVisibilityLevel $visibility = null): User
{
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore '.uniqid(), 'slug' => 'operatore-'.uniqid()]);
    $role->givePermission(Permission::where('key', "entity_{$entity->slug}.index")->firstOrFail());
    $user->assignRole($role);

    if ($visibility !== null) {
        EntityRoleVisibility::create(['entity_id' => $entity->id, 'role_id' => $role->id, 'level' => $visibility]);
    }

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('search', ['q' => 'mario']))->assertRedirect(route('login'));
});

test('a query shorter than 2 characters returns no results', function () {
    $entity = installedSearchableEntity();
    $user = userWithEntityPermission($entity);
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'nome' => 'Mario Rossi']);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'm']));

    $response->assertOk()->assertExactJson(['results' => []]);
});

test('a matching record is found and links to its edit page', function () {
    $entity = installedSearchableEntity();
    $user = userWithEntityPermission($entity);
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'nome' => 'Mario Rossi']);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'Mario']));

    $response->assertOk();
    $results = $response->json('results');

    expect($results)->toHaveCount(1);
    expect($results[0]['entity']['slug'])->toBe('contatti');
    expect($results[0]['records'])->toHaveCount(1);
    expect($results[0]['records'][0]['title'])->toBe('Mario Rossi');
    expect($results[0]['records'][0]['url'])->toBe(route('entities.edit', [$entity, $record->id]));
});

test('a non-matching term returns no results for that entity', function () {
    $entity = installedSearchableEntity();
    $user = userWithEntityPermission($entity);
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'nome' => 'Mario Rossi']);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'Luigi']));

    $response->assertOk()->assertExactJson(['results' => []]);
});

test('a user without the entity permission gets no results from it', function () {
    $entity = installedSearchableEntity();
    $owner = userWithEntityPermission($entity);
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $owner->id, 'nome' => 'Mario Rossi']);

    $outsider = User::factory()->create();

    $response = $this->actingAs($outsider)->getJson(route('search', ['q' => 'Mario']));

    $response->assertOk()->assertExactJson(['results' => []]);
});

test('own-only visibility hides other users records from search', function () {
    $entity = installedSearchableEntity();
    $owner = User::factory()->create();
    $user = userWithEntityPermission($entity, EntityVisibilityLevel::OwnOnly);
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $owner->id, 'nome' => 'Mario Rossi']);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'Mario']));

    $response->assertOk()->assertExactJson(['results' => []]);
});

test('own-only visibility still surfaces the users own records', function () {
    $entity = installedSearchableEntity();
    $user = userWithEntityPermission($entity, EntityVisibilityLevel::OwnOnly);
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'nome' => 'Mario Rossi']);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'Mario']));

    expect($response->json('results'))->toHaveCount(1);
});

test('results are grouped separately per matching entity', function () {
    $contatti = installedSearchableEntity('contatti', 'Contatti');
    $aziende = installedSearchableEntity('aziende', 'Aziende');

    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);
    $role->givePermission(Permission::where('key', 'entity_contatti.index')->firstOrFail());
    $role->givePermission(Permission::where('key', 'entity_aziende.index')->firstOrFail());
    $user->assignRole($role);

    EntityRecord::forEntity($contatti)->newQuery()->create(['user_id' => $user->id, 'nome' => 'Acme Support']);
    EntityRecord::forEntity($aziende)->newQuery()->create(['user_id' => $user->id, 'nome' => 'Acme Corp']);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'Acme']));

    $slugs = collect($response->json('results'))->pluck('entity.slug')->sort()->values()->all();
    expect($slugs)->toBe(['aziende', 'contatti']);
});

test('the search box is shown on the main app section but not in admin', function () {
    $user = adminUser();

    $this->actingAs($user)->get(route('dashboard'))->assertSee('id="global-search-input"', false);
    $this->actingAs($user)->get(route('admin.users.index'))->assertDontSee('id="global-search-input"', false);
})->skip('The search box itself is host-owned view logic (layouts/base.blade.php), not shipped by this package.');
