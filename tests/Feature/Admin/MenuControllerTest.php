<?php

use Fazzinipierluigi\CrmCore\Models\Entity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function installedEntity(string $name, string $slug, array $attributes = []): Entity
{
    return Entity::create(array_merge([
        'name' => $name,
        'slug' => $slug,
        'table_name' => "entity_{$slug}",
        'is_installed' => true,
    ], $attributes));
}

test('guests are redirected to login', function () {
    $this->get(route('admin.menu.edit'))->assertRedirect(route('login'));
});

test('admin can view the menu builder, split into visible and hidden entities', function () {
    $admin = adminUser();
    $visible = installedEntity('Contatti', 'contatti', ['show_in_menu' => true, 'menu_position' => 0]);
    $hidden = installedEntity('Fatture', 'fatture', ['show_in_menu' => false]);

    $response = $this->actingAs($admin)->get(route('admin.menu.edit'));

    $response->assertOk();
    $response->assertSee('data-testid="menu-item-contatti"', false);
    $response->assertSee('data-testid="menu-item-fatture"', false);
    $response->assertViewHas('visibleEntities', fn ($entities) => $entities->pluck('id')->contains($visible->id));
    $response->assertViewHas('hiddenEntities', fn ($entities) => $entities->pluck('id')->contains($hidden->id));
});

test('the calendar entity is configurable in the builder like any other entity', function () {
    $admin = adminUser();
    installedEntity('Calendario', 'calendario', ['is_calendar' => true, 'is_system' => true, 'show_in_menu' => true]);

    $response = $this->actingAs($admin)->get(route('admin.menu.edit'));

    $response->assertSee('data-testid="menu-item-calendario"', false);
});

test('admin can reorder and hide entities in the main menu', function () {
    $admin = adminUser();
    $contatti = installedEntity('Contatti', 'contatti');
    $fatture = installedEntity('Fatture', 'fatture');
    $ordini = installedEntity('Ordini', 'ordini');

    $response = $this->actingAs($admin)->put(route('admin.menu.update'), [
        'visible' => [$fatture->id, $contatti->id],
        'quick_access' => [],
    ]);

    $response->assertRedirect(route('admin.menu.edit'));

    $fatture = $fatture->fresh();
    $contatti = $contatti->fresh();
    expect($fatture->show_in_menu)->toBeTrue();
    expect($fatture->menu_position)->toBe(0);
    expect($contatti->show_in_menu)->toBeTrue();
    expect($contatti->menu_position)->toBe(1);
    expect($ordini->fresh()->show_in_menu)->toBeFalse();
});

test('admin can set and reorder quick access entities', function () {
    $admin = adminUser();
    $contatti = installedEntity('Contatti', 'contatti');
    $fatture = installedEntity('Fatture', 'fatture');

    $response = $this->actingAs($admin)->put(route('admin.menu.update'), [
        'visible' => [$contatti->id, $fatture->id],
        'quick_access' => [$fatture->id, $contatti->id],
    ]);

    $response->assertRedirect(route('admin.menu.edit'));

    $fatture = $fatture->fresh();
    $contatti = $contatti->fresh();
    expect($fatture->show_in_quick_access)->toBeTrue();
    expect($fatture->quick_access_position)->toBe(0);
    expect($contatti->show_in_quick_access)->toBeTrue();
    expect($contatti->quick_access_position)->toBe(1);
});

test('an entity dropped from quick access on save is unset', function () {
    $admin = adminUser();
    $contatti = installedEntity('Contatti', 'contatti', ['show_in_quick_access' => true, 'quick_access_position' => 0]);

    $response = $this->actingAs($admin)->put(route('admin.menu.update'), [
        'visible' => [$contatti->id],
        'quick_access' => [],
    ]);

    $response->assertRedirect(route('admin.menu.edit'));
    expect($contatti->fresh()->show_in_quick_access)->toBeFalse();
});

test('an uninstalled entity id is rejected', function () {
    $admin = adminUser();
    $notInstalled = Entity::create(['name' => 'Bozza', 'slug' => 'bozza', 'table_name' => 'entity_bozza']);

    $response = $this->actingAs($admin)->put(route('admin.menu.update'), [
        'visible' => [$notInstalled->id],
        'quick_access' => [],
    ]);

    $response->assertSessionHasErrors('visible.0');
});
