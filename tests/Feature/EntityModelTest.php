<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Enums\EntityVisibilityLevel;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityField;
use Fazzinipierluigi\CrmCore\Models\EntityRoleVisibility;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeEntity(array $attributes = []): Entity
{
    return Entity::create(array_merge([
        'name' => 'Contatti',
        'slug' => 'contatti',
        'table_name' => 'entity_contatti',
    ], $attributes));
}

test('an entity nests tabs, cards and fields in position order', function () {
    $entity = makeEntity();

    $tabB = EntityTab::create(['entity_id' => $entity->id, 'name' => 'B', 'position' => 1]);
    $tabA = EntityTab::create(['entity_id' => $entity->id, 'name' => 'A', 'position' => 0]);

    $cardB = EntityCard::create(['entity_tab_id' => $tabA->id, 'name' => 'B', 'position' => 1]);
    $cardA = EntityCard::create(['entity_tab_id' => $tabA->id, 'name' => 'A', 'position' => 0]);

    EntityField::create([
        'entity_card_id' => $cardA->id,
        'name' => 'Nome',
        'column_name' => 'nome',
        'type' => EntityFieldType::String,
        'position' => 0,
    ]);

    $entity->load('tabs.cards.fields');

    expect($entity->tabs->pluck('name')->all())->toBe(['A', 'B']);
    expect($entity->tabs->first()->cards->pluck('name')->all())->toBe(['A', 'B']);
    expect($entity->allFields()->pluck('column_name')->all())->toBe(['nome']);

    expect($tabB->entity->is($entity))->toBeTrue();
    expect($cardB->tab->is($tabA))->toBeTrue();
});

test('entity_fields casts type, options and required', function () {
    $entity = makeEntity();
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Tab', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Card', 'position' => 0]);

    $field = EntityField::create([
        'entity_card_id' => $card->id,
        'name' => 'Stato',
        'column_name' => 'stato',
        'type' => EntityFieldType::Select,
        'options' => ['open' => 'Aperto', 'closed' => 'Chiuso'],
        'required' => true,
        'position' => 0,
    ]);

    $field->refresh();

    expect($field->type)->toBe(EntityFieldType::Select);
    expect($field->options)->toBe(['open' => 'Aperto', 'closed' => 'Chiuso']);
    expect($field->required)->toBeTrue();
});

test('entity slug and table_name must be unique', function () {
    makeEntity();

    expect(fn () => makeEntity())->toThrow(QueryException::class);
});

test('field column_name must be unique within the same card', function () {
    $entity = makeEntity();
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Tab', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Card', 'position' => 0]);

    EntityField::create(['entity_card_id' => $card->id, 'name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    expect(fn () => EntityField::create(['entity_card_id' => $card->id, 'name' => 'Nome 2', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 1]))
        ->toThrow(QueryException::class);
});

test('deleting an entity cascades tabs, cards and fields', function () {
    $entity = makeEntity();
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Tab', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Card', 'position' => 0]);
    $field = EntityField::create(['entity_card_id' => $card->id, 'name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    $entity->delete();

    expect(EntityTab::find($tab->id))->toBeNull();
    expect(EntityCard::find($card->id))->toBeNull();
    expect(EntityField::find($field->id))->toBeNull();
});

test('a role can only have one visibility level per entity', function () {
    $entity = makeEntity();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);

    EntityRoleVisibility::create(['entity_id' => $entity->id, 'role_id' => $role->id, 'level' => EntityVisibilityLevel::OwnOnly]);

    expect(fn () => EntityRoleVisibility::create(['entity_id' => $entity->id, 'role_id' => $role->id, 'level' => EntityVisibilityLevel::Full]))
        ->toThrow(QueryException::class);
});

test('entity_role_visibility casts level and resolves its relations', function () {
    $entity = makeEntity();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);

    $visibility = EntityRoleVisibility::create(['entity_id' => $entity->id, 'role_id' => $role->id, 'level' => EntityVisibilityLevel::OwnManageOthersEdit]);
    $visibility->refresh();

    expect($visibility->level)->toBe(EntityVisibilityLevel::OwnManageOthersEdit);
    expect($visibility->entity->is($entity))->toBeTrue();
    expect($visibility->role->is($role))->toBeTrue();
});
