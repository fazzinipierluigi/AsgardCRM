<?php

use App\Models\User;
use Fazzinipierluigi\CrmCore\Database\Seeders\ClientiEntitySeeder;
use Fazzinipierluigi\CrmCore\Database\Seeders\PreventiviEntitySeeder;
use Fazzinipierluigi\CrmCore\Database\Seeders\ProdottiEntitySeeder;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Rules\ProductsBlockRule;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedPreventiviChain(): void
{
    test()->seed(ProdottiEntitySeeder::class);
    test()->seed(ClientiEntitySeeder::class);
    test()->seed(PreventiviEntitySeeder::class);
}

function userWithSlugPermissions(string $slug, array $actions = ['index', 'create', 'edit', 'delete']): User
{
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore-'.uniqid()]);

    foreach ($actions as $action) {
        $role->givePermission(Permission::where('key', "entity_{$slug}.{$action}")->firstOrFail());
    }

    $user->assignRole($role);

    return $user;
}

test('a row with quantity/unit_price passes and computes the expected subtotal', function () {
    $rule = new ProductsBlockRule(extraColumns: [], requireAtLeastOne: false, catalogTable: null);
    $failed = null;

    $rule->validate('righe_prodotto', json_encode([
        ['product_id' => 1, 'quantity' => 2, 'unit_price' => 10.5],
    ]), function ($message) use (&$failed) {
        $failed = $message;
    });

    expect($failed)->toBeNull();
});

test('requireAtLeastOne rejects an empty block', function () {
    $rule = new ProductsBlockRule(requireAtLeastOne: true);
    $failed = null;

    $rule->validate('righe_prodotto', '[]', function ($message) use (&$failed) {
        $failed = $message;
    });

    expect($failed)->not->toBeNull();
});

test('a row with zero or negative quantity fails', function () {
    $rule = new ProductsBlockRule;
    $failed = null;

    $rule->validate('righe_prodotto', json_encode([
        ['product_id' => 1, 'quantity' => 0, 'unit_price' => 10],
    ]), function ($message) use (&$failed) {
        $failed = $message;
    });

    expect($failed)->not->toBeNull();
});

test('a row without a product but with a name passes, as a custom line item', function () {
    $rule = new ProductsBlockRule;
    $failed = null;

    $rule->validate('righe_prodotto', json_encode([
        ['name' => 'Servizio su misura', 'description' => 'Lavorazione custom', 'quantity' => 1, 'unit_price' => 50],
    ]), function ($message) use (&$failed) {
        $failed = $message;
    });

    expect($failed)->toBeNull();
});

test('a row without a product, name or description fails', function () {
    $rule = new ProductsBlockRule;
    $failed = null;

    $rule->validate('righe_prodotto', json_encode([
        ['quantity' => 1, 'unit_price' => 50],
    ]), function ($message) use (&$failed) {
        $failed = $message;
    });

    expect($failed)->not->toBeNull();
});

test('a purely descriptive row without quantity or price passes', function () {
    $rule = new ProductsBlockRule;
    $failed = null;

    $rule->validate('righe_prodotto', json_encode([
        ['name' => 'Sezione: Materiali', 'description' => 'Le righe seguenti riguardano i materiali'],
    ]), function ($message) use (&$failed) {
        $failed = $message;
    });

    expect($failed)->toBeNull();
});

test('a row referencing a non-existent product in the catalog table fails', function () {
    seedPreventiviChain();
    $catalogTable = Entity::where('slug', 'prodotti')->firstOrFail()->table_name;
    $rule = new ProductsBlockRule(catalogTable: $catalogTable);
    $failed = null;

    $rule->validate('righe_prodotto', json_encode([
        ['product_id' => 999999, 'quantity' => 1, 'unit_price' => 10],
    ]), function ($message) use (&$failed) {
        $failed = $message;
    });

    expect($failed)->not->toBeNull();
});

test('creating a Preventivo requires at least one product row', function () {
    seedPreventiviChain();
    $user = userWithSlugPermissions('preventivi');
    $cliente = EntityRecord::forEntity(Entity::where('slug', 'clienti')->firstOrFail())->newQuery()->create([
        'user_id' => $user->id,
        'ragione_sociale' => 'Rossi Srl',
    ]);

    $response = $this->actingAs($user)->post(route('entities.store', 'preventivi'), [
        'titolo' => 'Preventivo senza righe',
        'cliente_id' => $cliente->id,
        'data_preventivo' => '2026-08-01',
        'righe_prodotto' => '[]',
        'stato' => 'bozza',
    ]);

    $response->assertSessionHasErrors(['righe_prodotto']);
});

test('creating a Preventivo with a product row succeeds and syncs the total', function () {
    seedPreventiviChain();
    $user = userWithSlugPermissions('preventivi');
    $clientiEntity = Entity::where('slug', 'clienti')->firstOrFail();
    $prodottiEntity = Entity::where('slug', 'prodotti')->firstOrFail();

    $cliente = EntityRecord::forEntity($clientiEntity)->newQuery()->create([
        'user_id' => $user->id,
        'ragione_sociale' => 'Rossi Srl',
    ]);

    $prodotto = EntityRecord::forEntity($prodottiEntity)->newQuery()->create([
        'user_id' => $user->id,
        'nome' => 'Widget',
        'prezzo_di_listino' => 10.5,
        'stato' => 'attivo',
    ]);

    $response = $this->actingAs($user)->post(route('entities.store', 'preventivi'), [
        'titolo' => 'Preventivo con righe',
        'cliente_id' => $cliente->id,
        'data_preventivo' => '2026-08-01',
        'righe_prodotto' => json_encode([
            ['product_id' => $prodotto->id, 'quantity' => 3, 'unit_price' => 10.5, 'subtotal' => 31.5],
        ]),
        'totale' => '31.50',
        'stato' => 'bozza',
    ]);

    $response->assertRedirect(route('entities.index', 'preventivi'));

    $preventiviEntity = Entity::where('slug', 'preventivi')->firstOrFail();
    $record = EntityRecord::forEntity($preventiviEntity)->newQuery()->where('titolo', 'Preventivo con righe')->firstOrFail();

    expect(json_decode($record->righe_prodotto, true))->toHaveCount(1);
    expect((float) $record->totale)->toBe(31.5);
});

test('the Blocco Prodotti widget receives each catalog product\'s description', function () {
    seedPreventiviChain();
    $user = userWithSlugPermissions('preventivi');
    $prodottiEntity = Entity::where('slug', 'prodotti')->firstOrFail();

    EntityRecord::forEntity($prodottiEntity)->newQuery()->create([
        'user_id' => $user->id,
        'nome' => 'Widget',
        'descrizione' => 'Descrizione dettagliata del widget',
        'prezzo_di_listino' => 10.5,
        'stato' => 'attivo',
    ]);

    $response = $this->actingAs($user)->get(route('entities.create', 'preventivi'));

    $response->assertOk()->assertSee('Descrizione dettagliata del widget');
});
