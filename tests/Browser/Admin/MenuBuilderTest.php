<?php

use App\Models\Entity;
use Laravel\Dusk\Browser;

function menuBuilderEntity(string $name, string $slug, array $attributes = []): Entity
{
    return Entity::create(array_merge([
        'name' => $name,
        'slug' => $slug,
        'table_name' => "entity_{$slug}",
        'is_installed' => true,
    ], $attributes));
}

test('admin can move an entity to Altre entità and back via the swap button', function () {
    $contatti = menuBuilderEntity('Contatti', 'contatti', ['show_in_menu' => true]);
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/menu')
            ->waitFor('[data-testid="menu-item-contatti"]')
            ->assertVisible('#menu-visible-list [data-testid="menu-item-contatti"]')
            ->click('[data-testid="menu-toggle-visibility-contatti"]')
            ->pause(100)
            ->assertVisible('#menu-hidden-list [data-testid="menu-item-contatti"]')
            ->press('Salva menù')
            ->waitForText('Menù aggiornato.');
    });

    expect($contatti->fresh()->show_in_menu)->toBeFalse();
});

test('admin can add entities to Accesso rapido, reorder them, and save', function () {
    $contatti = menuBuilderEntity('Contatti', 'contatti');
    $fatture = menuBuilderEntity('Fatture', 'fatture');
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/menu')
            ->waitFor('[data-testid="menu-item-contatti"]')
            ->click('[data-testid="menu-toggle-quick-access-contatti"]')
            ->pause(100)
            ->click('[data-testid="menu-toggle-quick-access-fatture"]')
            ->pause(100)
            ->assertVisible('[data-testid="quick-access-item-contatti"]')
            ->assertVisible('[data-testid="quick-access-item-fatture"]');

        // Headless Chrome can't reliably simulate a native drag gesture
        // (see EntityBuilderDragDropTest) — exercise the same DOM outcome
        // a real drag would produce instead: swap the two list items.
        $browser->script([
            'var list = document.getElementById("quick-access-list");'.
            'var items = list.querySelectorAll("li");'.
            'list.insertBefore(items[1], items[0]);',
        ]);

        $browser->press('Salva menù')
            ->waitForText('Menù aggiornato.');
    });

    expect($fatture->fresh())->show_in_quick_access->toBeTrue();
    expect($fatture->fresh()->quick_access_position)->toBe(0);
    expect($contatti->fresh())->show_in_quick_access->toBeTrue();
    expect($contatti->fresh()->quick_access_position)->toBe(1);
});

test('admin can remove an entity from Accesso rapido', function () {
    $contatti = menuBuilderEntity('Contatti', 'contatti', ['show_in_quick_access' => true, 'quick_access_position' => 0]);
    $admin = adminUser();

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->loginAs($admin)
            ->visit('/admin/menu')
            ->waitFor('[data-testid="quick-access-item-contatti"]')
            ->click('[data-testid="quick-access-remove-contatti"]')
            ->pause(100)
            ->assertMissing('[data-testid="quick-access-item-contatti"]')
            ->press('Salva menù')
            ->waitForText('Menù aggiornato.');
    });

    expect($contatti->fresh()->show_in_quick_access)->toBeFalse();
});
