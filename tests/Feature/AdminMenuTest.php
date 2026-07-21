<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
