<?php

use Fazzinipierluigi\CrmCore\Models\EntityRelation;
use Fazzinipierluigi\CrmCore\Models\EntityRelationLink;
use App\Models\User;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an admin can create a relation definition between two entities', function () {
    $clienti = relationTestEntity('rel-clienti', 'Clienti');
    $prodotti = relationTestEntity('rel-prodotti', 'Prodotti');

    $response = $this->actingAs(adminUser())->post(route('admin.entities.relations.store', $clienti), [
        'name' => 'Prodotti acquistati',
        'entity_b_id' => $prodotti->id,
    ]);

    $response->assertRedirect(route('admin.entities.relations.index', $clienti));
    $relation = EntityRelation::firstOrFail();
    expect($relation->entity_a_id)->toBe($clienti->id);
    expect($relation->entity_b_id)->toBe($prodotti->id);
    expect($relation->name)->toBe('Prodotti acquistati');
});

test('a relation cannot target the same entity it is created from', function () {
    $clienti = relationTestEntity('rel-clienti-self', 'Clienti');

    $response = $this->actingAs(adminUser())->post(route('admin.entities.relations.store', $clienti), [
        'name' => 'Auto relazione',
        'entity_b_id' => $clienti->id,
    ]);

    $response->assertSessionHasErrors('entity_b_id');
});

test('an admin can update a relation created from the other side without repointing it away from the original entity', function () {
    $clienti = relationTestEntity('rel-clienti-upd', 'Clienti');
    $prodotti = relationTestEntity('rel-prodotti-upd', 'Prodotti');
    $ordini = relationTestEntity('rel-ordini-upd', 'Ordini');

    $relation = EntityRelation::create(['entity_a_id' => $prodotti->id, 'entity_b_id' => $clienti->id, 'name' => 'Vecchio nome']);

    // Editing from $clienti's admin page ($clienti is entity_b here) must
    // repoint entity_a_id (the *other* side), not entity_b_id, or the
    // relation would stop touching $clienti entirely.
    $response = $this->actingAs(adminUser())->put(route('admin.entities.relations.update', [$clienti, $relation]), [
        'name' => 'Nuovo nome',
        'entity_b_id' => $ordini->id,
    ]);

    $response->assertRedirect(route('admin.entities.relations.index', $clienti));
    $relation->refresh();
    expect($relation->entity_a_id)->toBe($ordini->id);
    expect($relation->entity_b_id)->toBe($clienti->id);
    expect($relation->name)->toBe('Nuovo nome');
});

test('deleting a relation cascades and deletes its links', function () {
    $clienti = relationTestEntity('rel-clienti-del', 'Clienti');
    $prodotti = relationTestEntity('rel-prodotti-del', 'Prodotti');
    $relation = EntityRelation::create(['entity_a_id' => $clienti->id, 'entity_b_id' => $prodotti->id, 'name' => 'Acquisti']);
    $relation->links()->create(['entity_a_record_id' => 1, 'entity_b_record_id' => 1]);

    $this->actingAs(adminUser())->delete(route('admin.entities.relations.destroy', [$clienti, $relation]))
        ->assertRedirect(route('admin.entities.relations.index', $clienti));

    expect(EntityRelation::find($relation->id))->toBeNull();
    expect(EntityRelationLink::count())->toBe(0);
});

test('a non-admin user is forbidden from the relations admin', function () {
    $clienti = relationTestEntity('rel-clienti-forbidden', 'Clienti');
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.entities.relations.index', $clienti))->assertForbidden();
});

test('a user with the relevant admin permission can access the relations admin', function () {
    $clienti = relationTestEntity('rel-clienti-allowed', 'Clienti');
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore-relations']);
    $permission = Permission::firstOrCreate(['key' => 'entityrelation.index'], ['name' => 'Vedi relazioni']);
    $role->givePermission($permission);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.entities.relations.index', $clienti))->assertOk();
});
