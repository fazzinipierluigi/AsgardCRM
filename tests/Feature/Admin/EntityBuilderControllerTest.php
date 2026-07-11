<?php

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityCard;
use App\Models\EntityTab;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function entityWithTree(): Entity
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => 'string', 'position' => 0]);

    return $entity;
}

function simpleTreePayload(): array
{
    return [
        'tabs' => [
            't1' => [
                'name' => 'Generale',
                'cards' => [
                    'c1' => [
                        'name' => 'Anagrafica',
                        'fields' => [
                            'f1' => [
                                'name' => 'Nome',
                                'column_name' => 'nome',
                                'type' => 'string',
                                'required' => '1',
                                'default_value' => '',
                            ],
                            'f2' => [
                                'name' => 'Stato',
                                'column_name' => 'stato',
                                'type' => 'select',
                                'options' => "open:Aperto\nclosed:Chiuso",
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

test('guests are redirected to login', function () {
    $entity = entityWithTree();

    $this->get(route('admin.entities.builder.edit', $entity))->assertRedirect(route('login'));
});

test('admin can view the builder page', function () {
    $entity = entityWithTree();

    $this->actingAs(adminUser())->get(route('admin.entities.builder.edit', $entity))->assertOk();
});

test('admin can save a tab/card/field tree', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), simpleTreePayload());

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));
    $entity->load('tabs.cards.fields');

    expect($entity->tabs)->toHaveCount(1);
    expect($entity->tabs->first()->cards)->toHaveCount(1);

    $fields = $entity->tabs->first()->cards->first()->fields;
    expect($fields)->toHaveCount(2);

    $nome = $fields->firstWhere('column_name', 'nome');
    expect($nome->required)->toBeTrue();

    $stato = $fields->firstWhere('column_name', 'stato');
    expect($stato->options)->toBe(['open' => 'Aperto', 'closed' => 'Chiuso']);
});

test('saving a new tree replaces the previous one entirely', function () {
    $admin = adminUser();
    $entity = entityWithTree();

    $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), [
        'tabs' => [
            't1' => [
                'name' => 'Nuovo',
                'cards' => [
                    'c1' => [
                        'name' => 'Card',
                        'fields' => [
                            'f1' => ['name' => 'Cognome', 'column_name' => 'cognome', 'type' => 'string'],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $entity->load('tabs.cards.fields');
    expect($entity->tabs)->toHaveCount(1);
    expect($entity->tabs->first()->name)->toBe('Nuovo');
    expect($entity->allFields()->pluck('column_name')->all())->toBe(['cognome']);
});

test('at least one tab is required', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), ['tabs' => []]);

    $response->assertSessionHasErrors('tabs');
});

test('a card must have at least one field', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields'] = [];

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.t1.cards.c1.fields');
});

test('a reserved column name is rejected', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f1']['column_name'] = 'user_id';

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.t1.cards.c1.fields.f1.column_name');
});

test('column names must be unique within the entity', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['column_name'] = 'nome';

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.t1.cards.c1.fields.f2.column_name');
});

test('a select field requires options', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['options'] = '';

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.t1.cards.c1.fields.f2.options');
});

test('a relation field requires a target', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['type'] = 'relation';
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['options'] = '';

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.t1.cards.c1.fields.f2.relation_target');
});

test('a relation field persists its target type and target', function () {
    $admin = adminUser();
    $other = Entity::create(['name' => 'Aziende', 'slug' => 'aziende', 'table_name' => 'entity_aziende']);
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['type'] = 'relation';
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['options'] = '';
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['relation_target'] = "entity:{$other->slug}";

    $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $field = $entity->allFields()->firstWhere('column_name', 'stato');
    expect($field->relation_target_type->value)->toBe('entity');
    expect($field->relation_target)->toBe('aziende');
});

test('a code field persists its prefix into options', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Fatture', 'slug' => 'fatture', 'table_name' => 'entity_fatture']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['type'] = 'code';
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['options'] = '';
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['code_prefix'] = 'INV-';

    $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $field = $entity->allFields()->firstWhere('column_name', 'stato');
    expect($field->type)->toBe(EntityFieldType::Code);
    expect($field->options)->toBe(['prefix' => 'INV-']);
});

test('a field width is persisted', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f1']['width'] = '6';
    $payload['tabs']['t1']['cards']['c1']['fields']['f2']['width'] = '4';

    $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    expect($entity->allFields()->firstWhere('column_name', 'nome')->width)->toBe(6);
    expect($entity->allFields()->firstWhere('column_name', 'stato')->width)->toBe(4);
});

test('a field width defaults to 12 when not submitted', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), simpleTreePayload());

    expect($entity->allFields()->firstWhere('column_name', 'nome')->width)->toBe(12);
});

test('an out-of-range field width is rejected', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $payload = simpleTreePayload();
    $payload['tabs']['t1']['cards']['c1']['fields']['f1']['width'] = '99';

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), $payload);

    $response->assertSessionHasErrors('tabs.t1.cards.c1.fields.f1.width');
});

test('an installed entity structure cannot be modified', function () {
    $admin = adminUser();
    $entity = entityWithTree();
    $entity->update(['is_installed' => true]);
    $originalFieldCount = $entity->allFields()->count();

    $response = $this->actingAs($admin)->put(route('admin.entities.builder.update', $entity), simpleTreePayload());

    $response->assertSessionHasErrors('tabs');
    expect($entity->fresh()->allFields()->count())->toBe($originalFieldCount);
});
