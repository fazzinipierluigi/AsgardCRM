<?php

use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Models\EntityRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the record detail page shows no relations sidebar when the entity has no relation defined', function () {
    $entity = relationTestEntity('sidebar-none', 'Senza relazioni');
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Test']);

    $this->actingAs($admin)->get(route('entities.edit', [$entity, $record]))
        ->assertOk()
        ->assertDontSee('data-testid="entity-relations-card"', false);
});

test('the record detail page shows the relations sidebar with the current link count', function () {
    $clienti = relationTestEntity('sidebar-clienti', 'Clienti');
    $prodotti = relationTestEntity('sidebar-prodotti', 'Prodotti');
    $relation = EntityRelation::create(['entity_a_id' => $clienti->id, 'entity_b_id' => $prodotti->id, 'name' => 'Acquisti']);

    $admin = adminUser();
    $clienteRecord = EntityRecord::forEntity($clienti)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Mario']);
    $prodottoRecord = EntityRecord::forEntity($prodotti)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Sedia']);
    $relation->links()->create(['entity_a_record_id' => $clienteRecord->id, 'entity_b_record_id' => $prodottoRecord->id]);

    $response = $this->actingAs($admin)->get(route('entities.edit', [$clienti, $clienteRecord]));

    $response->assertOk()
        ->assertSee('data-testid="entity-relations-card"', false)
        ->assertSee('Acquisti')
        ->assertSeeInOrder(['data-entity-relation-count', '1'], false);
});
