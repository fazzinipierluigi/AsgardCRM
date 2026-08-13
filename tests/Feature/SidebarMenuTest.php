<?php

use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The sidebar menu itself (looping installed entities, quick-access
// icons, inline-svg icon rendering) is rendered by the host's own
// layouts/base.blade.php — a documented host contract, never shipped
// by this package (see tests/resources/views for the minimal
// structural stub used elsewhere in this suite; it deliberately
// doesn't replicate this business logic). Belongs in a real host's
// own test suite (e.g. AsgardCRM-Scaffolding) once one exists.
uses(RefreshDatabase::class)->beforeEach(fn () => test()->markTestSkipped(
    'Sidebar menu rendering is host-owned view logic, not shipped by this package.'
));

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
    $response->assertSee('<div class="dropdown-menu collapse " id="other-entities-menu">', false);
});

test('the Altre entità group is expanded while browsing one of its entities', function () {
    $admin = adminUser();
    $entity = installedMenuEntity('Fatture', 'fatture', ['show_in_menu' => false]);

    $response = $this->actingAs($admin)->get(route('entities.index', $entity));

    $response->assertSee('<div class="dropdown-menu collapse show" id="other-entities-menu">', false);
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

/**
 * Regression test: the quick-access topbar icon used to only
 * special-case is_calendar, silently falling back to the generic
 * entities.index (Raccoon-grid) URL for is_documents/is_email —
 * meaning Documenti/E-mail opened as a plain data table instead of
 * their own dedicated UI when launched from quick access, even though
 * the main sidebar link (which already went through Entity::indexUrl())
 * opened the right page.
 */
test('quick access opens a system entity\'s own dedicated page, not the generic grid', function () {
    $admin = adminUser();
    installedMenuEntity('Documenti', 'documenti', ['show_in_quick_access' => true, 'is_documents' => true]);
    installedMenuEntity('E-mail', 'email', ['show_in_quick_access' => true, 'is_email' => true]);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertSee('data-url="'.route('documents.index', ['embed' => 1]).'"', false);
    $response->assertSee('data-url="'.route('mail.index', ['embed' => 1]).'"', false);
    $response->assertDontSee('data-url="'.route('entities.index', ['documenti', 'embed' => 1]).'"', false);
    $response->assertDontSee('data-url="'.route('entities.index', ['email', 'embed' => 1]).'"', false);
});

test('an entity icon renders as inline svg in the sidebar menu, not a webfont class', function () {
    $admin = adminUser();
    installedMenuEntity('Contatti', 'contatti', ['show_in_menu' => true, 'icon' => 'building']);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertSee(icon('building'), false);
    $response->assertDontSee('<i class="building">', false);
});
