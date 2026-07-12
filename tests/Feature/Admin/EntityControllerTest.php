<?php

use App\Models\Entity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.entities.index'))->assertRedirect(route('login'));
});

test('admin can view the entities index', function () {
    $this->actingAs(adminUser())->get(route('admin.entities.index'))->assertOk();
});

test('admin can create an entity and its slug/table_name are auto-generated', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.entities.store'), [
        'name' => 'Contatti',
    ]);

    $entity = Entity::where('name', 'Contatti')->firstOrFail();
    $response->assertRedirect(route('admin.entities.builder.edit', $entity));
    expect($entity->slug)->toBe('contatti');
    expect($entity->table_name)->toBe('entity_contatti');
    expect($entity->is_system)->toBeFalse();
    expect($entity->is_installed)->toBeFalse();
});

test('auto-generated slugs are unique even for the same name', function () {
    $admin = adminUser();

    $this->actingAs($admin)->post(route('admin.entities.store'), ['name' => 'Contatti']);
    $this->actingAs($admin)->post(route('admin.entities.store'), ['name' => 'Contatti']);

    $slugs = Entity::where('name', 'Contatti')->pluck('slug')->sort()->values();
    expect($slugs->all())->toBe(['contatti', 'contatti-2']);
});

test('admin can update an entity name and icon but not its slug', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $response = $this->actingAs($admin)->put(route('admin.entities.update', $entity), [
        'name' => 'Aziende',
        'icon' => 'building',
    ]);

    $response->assertRedirect(route('admin.entities.index'));
    expect($entity->fresh()->name)->toBe('Aziende');
    expect($entity->fresh()->icon)->toBe('building');
    expect($entity->fresh()->slug)->toBe('contatti');
});

test('a system entity cannot be deleted', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti', 'is_system' => true]);

    $response = $this->actingAs($admin)->delete(route('admin.entities.destroy', $entity));

    $response->assertRedirect();
    expect(Entity::find($entity->id))->not->toBeNull();
});

test('an installed entity cannot be deleted', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti', 'is_installed' => true]);

    $response = $this->actingAs($admin)->delete(route('admin.entities.destroy', $entity));

    $response->assertRedirect();
    expect(Entity::find($entity->id))->not->toBeNull();
});

test('a custom uninstalled entity can be deleted', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $this->actingAs($admin)->delete(route('admin.entities.destroy', $entity));

    expect(Entity::find($entity->id))->toBeNull();
});

test('entities datatable endpoint returns json data', function () {
    $admin = adminUser();
    Entity::create(['name' => 'Findable Entity', 'slug' => 'findable-entity', 'table_name' => 'entity_findable_entity']);

    $response = $this->actingAs($admin)->getJson(route('admin.entities.data', ['start' => 0, 'limit' => 25]));

    $response->assertOk()->assertJsonStructure(['data', 'total']);
    expect(collect($response->json('data'))->pluck('name'))->toContain('Findable Entity');
});

test('the create form renders the icon select with every available icon', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->get(route('admin.entities.create'));

    $response->assertOk();
    $response->assertSee('data-tom-select-manual', false);
    $response->assertSee('<option value="building" >building</option>', false);
    expect(substr_count($response->getContent(), '<option value='))->toBe(count(icon_names()) + 1);
});

test('the edit form pre-selects the entity current icon', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti', 'icon' => 'building']);

    $response = $this->actingAs($admin)->get(route('admin.entities.edit', $entity));

    $response->assertOk();
    $response->assertSee('<option value="building" selected>building</option>', false);
});

test('creating an entity rejects an icon name that is not a real tabler icon', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.entities.store'), [
        'name' => 'Contatti',
        'icon' => 'ti ti-not-a-real-icon',
    ]);

    $response->assertSessionHasErrors('icon');
    expect(Entity::where('name', 'Contatti')->exists())->toBeFalse();
});

test('an entity can be created without an icon', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->post(route('admin.entities.store'), [
        'name' => 'Contatti',
        'icon' => '',
    ]);

    $response->assertSessionDoesntHaveErrors('icon');
    expect(Entity::where('name', 'Contatti')->firstOrFail()->icon)->toBeNull();
});
