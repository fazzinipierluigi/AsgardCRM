<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

// The admin subheader grouping (e.g. an "Accessi" section wrapping
// Users/Roles/Login-providers) is rendered by the host's own
// layouts/app.blade.php — a documented host contract, never shipped
// by this package (see tests/resources/views/layouts for the minimal
// structural stub used elsewhere in this suite; it deliberately
// doesn't replicate this business logic). Belongs in a real host's
// own test suite (e.g. AsgardCRM-Scaffolding) once one exists.
uses(RefreshDatabase::class)->beforeEach(fn () => test()->markTestSkipped(
    'Admin subheader grouping is host-owned view logic, not shipped by this package.'
));

test('admin menu groups items under section titles', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->get('/admin/users');

    $response->assertOk();
    $response->assertSeeInOrder([
        'Accessi',
        'Utenti',
        'Ruoli',
        'Localizzazione',
        'Traduzioni',
        'Lingue',
        'Struttura dati',
        'Entità',
        'Integrazioni',
        'Connettori',
    ]);
});

test('admin menu section titles use the subheader style', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)->get('/admin/users');

    $response->assertSee('<div class="subheader ps-2 mt-3">Accessi</div>', false);
    $response->assertSee('<div class="subheader ps-2 mt-3">Localizzazione</div>', false);
    $response->assertSee('<div class="subheader ps-2 mt-3">Struttura dati</div>', false);
    $response->assertSee('<div class="subheader ps-2 mt-3">Integrazioni</div>', false);
});
