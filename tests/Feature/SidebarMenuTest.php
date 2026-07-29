<?php

use App\Models\Entity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function installedMenuEntity(string $name, string $slug, array $attributes = []): Entity
{
    return Entity::create(array_merge([
        'name' => $name,
        'slug' => $slug,
        'table_name' => "entity_{$slug}",
        'is_installed' => true,
    ], $attributes));
}

test('a visible entity appears directly in the main menu', function () {
    $admin = adminUser();
    installedMenuEntity('Contatti', 'contatti', ['show_in_menu' => true]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertSee('data-testid="menu-entity-contatti"', false);
    $response->assertDontSee('data-testid="menu-other-entities"', false);
});

test('a hidden entity appears under the collapsed Altre entità group', function () {
    $admin = adminUser();
    installedMenuEntity('Fatture', 'fatture', ['show_in_menu' => false]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertSee('data-testid="menu-other-entities"', false);
    $response->assertSee('data-testid="menu-entity-fatture"', false);
    $response->assertSee('<div class="collapse " id="other-entities-menu">', false);
});

test('the Altre entità group is expanded while browsing one of its entities', function () {
    $admin = adminUser();
    $entity = installedMenuEntity('Fatture', 'fatture', ['show_in_menu' => false]);

    $response = $this->actingAs($admin)->get(route('entities.index', $entity));

    $response->assertSee('<div class="collapse show" id="other-entities-menu">', false);
    $response->assertSee('data-testid="menu-other-entities"', false);
});

test('quick access icons appear in the topbar', function () {
    $admin = adminUser();
    installedMenuEntity('Contatti', 'contatti', ['show_in_quick_access' => true]);
    installedMenuEntity('Fatture', 'fatture', ['show_in_quick_access' => false]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertSee('data-testid="quick-access-contatti"', false);
    $response->assertDontSee('data-testid="quick-access-fatture"', false);
});
