<?php

use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityRelation;
use Fazzinipierluigi\CrmCore\Models\EntityRelationLink;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Fazzinipierluigi\CrmCore\Tests\Fixtures\User;

uses(RefreshDatabase::class);

function relationLinkSetup(): array
{
    $clienti = relationTestEntity('link-clienti', 'Clienti');
    $prodotti = relationTestEntity('link-prodotti', 'Prodotti');
    $relation = EntityRelation::create(['entity_a_id' => $clienti->id, 'entity_b_id' => $prodotti->id, 'name' => 'Acquisti']);

    $admin = adminUser();
    $clienteRecord = EntityRecord::forEntity($clienti)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Mario Rossi']);
    $prodottoRecord = EntityRecord::forEntity($prodotti)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Sedia']);

    return compact('clienti', 'prodotti', 'relation', 'admin', 'clienteRecord', 'prodottoRecord');
}

test('attaching a record creates a link, and data lists it', function () {
    ['clienti' => $clienti, 'prodotti' => $prodotti, 'relation' => $relation, 'admin' => $admin, 'clienteRecord' => $clienteRecord, 'prodottoRecord' => $prodottoRecord] = relationLinkSetup();

    $this->actingAs($admin)->postJson(route('entities.relations.attach', [$clienti, $clienteRecord, $relation]), [
        'target_record_id' => $prodottoRecord->id,
    ])->assertOk();

    expect(EntityRelationLink::count())->toBe(1);

    $response = $this->actingAs($admin)->getJson(route('entities.relations.data', [$clienti, $clienteRecord, $relation]));

    $response->assertOk();
    expect($response->json())->toHaveCount(1);
    expect($response->json('0.label'))->toBe('Sedia');
    expect($response->json('0.record_id'))->toBe($prodottoRecord->id);
    expect($response->json('0.url'))->toBe(route('entities.show', [$prodotti, $prodottoRecord]));
});

test('attaching the same pair twice does not create a duplicate link', function () {
    ['clienti' => $clienti, 'relation' => $relation, 'admin' => $admin, 'clienteRecord' => $clienteRecord, 'prodottoRecord' => $prodottoRecord] = relationLinkSetup();

    $url = route('entities.relations.attach', [$clienti, $clienteRecord, $relation]);
    $this->actingAs($admin)->postJson($url, ['target_record_id' => $prodottoRecord->id])->assertOk();
    $this->actingAs($admin)->postJson($url, ['target_record_id' => $prodottoRecord->id])->assertOk();

    expect(EntityRelationLink::count())->toBe(1);
});

test('detaching removes the link', function () {
    ['clienti' => $clienti, 'relation' => $relation, 'admin' => $admin, 'clienteRecord' => $clienteRecord, 'prodottoRecord' => $prodottoRecord] = relationLinkSetup();

    $link = $relation->links()->create(['entity_a_record_id' => $clienteRecord->id, 'entity_b_record_id' => $prodottoRecord->id]);

    $this->actingAs($admin)->deleteJson(route('entities.relations.detach', [$clienti, $clienteRecord, $relation, $link]))->assertOk();

    expect(EntityRelationLink::count())->toBe(0);
});

test('options excludes already-linked records and matches the search term', function () {
    ['clienti' => $clienti, 'prodotti' => $prodotti, 'relation' => $relation, 'admin' => $admin, 'clienteRecord' => $clienteRecord, 'prodottoRecord' => $prodottoRecord] = relationLinkSetup();

    $otherProduct = EntityRecord::forEntity($prodotti)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Tavolo']);
    $relation->links()->create(['entity_a_record_id' => $clienteRecord->id, 'entity_b_record_id' => $prodottoRecord->id]);

    $response = $this->actingAs($admin)->getJson(route('entities.relations.options', [$clienti, $clienteRecord, $relation]));

    $response->assertOk();
    $ids = collect($response->json())->pluck('id');
    expect($ids)->not->toContain($prodottoRecord->id);
    expect($ids)->toContain($otherProduct->id);

    $searchResponse = $this->actingAs($admin)->getJson(route('entities.relations.options', [$clienti, $clienteRecord, $relation]).'?q=Tav');
    expect(collect($searchResponse->json())->pluck('id')->all())->toBe([$otherProduct->id]);
});

test('a user without edit permission on the entity is forbidden from managing its relations', function () {
    ['clienti' => $clienti, 'relation' => $relation, 'admin' => $admin, 'clienteRecord' => $clienteRecord, 'prodottoRecord' => $prodottoRecord] = relationLinkSetup();
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('entities.relations.data', [$clienti, $clienteRecord, $relation]))->assertForbidden();
});

test('a user with the entity edit permission can manage its relations', function () {
    ['clienti' => $clienti, 'relation' => $relation, 'clienteRecord' => $clienteRecord] = relationLinkSetup();
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore-link-clienti']);
    $role->givePermission(Permission::where('key', 'entity_link-clienti.edit')->firstOrFail());
    $user->assignRole($role);

    $this->actingAs($user)->getJson(route('entities.relations.data', [$clienti, $clienteRecord, $relation]))->assertOk();
});

test('a relation that does not belong to the entity 404s', function () {
    ['clienti' => $clienti, 'admin' => $admin, 'clienteRecord' => $clienteRecord] = relationLinkSetup();
    $unrelated = relationTestEntity('link-unrelated', 'Unrelated');
    $anotherUnrelated = relationTestEntity('link-unrelated-2', 'Unrelated 2');
    $otherRelation = EntityRelation::create(['entity_a_id' => $unrelated->id, 'entity_b_id' => $anotherUnrelated->id, 'name' => 'X']);

    $this->actingAs($admin)->getJson(route('entities.relations.data', [$clienti, $clienteRecord, $otherRelation]))->assertNotFound();
});
