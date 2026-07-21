<?php

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityCard;
use App\Models\EntityTab;
use App\Services\EntityInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function installedEntityWithCard(): array
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    return [$entity->fresh(), $card];
}

test('guests are redirected to login', function () {
    [$entity] = installedEntityWithCard();

    $this->get(route('admin.entities.fields.create', $entity))->assertRedirect(route('login'));
});

test('admin can view the add-field form for an installed entity', function () {
    [$entity] = installedEntityWithCard();

    $this->actingAs(adminUser())->get(route('admin.entities.fields.create', $entity))->assertOk();
});

test('adding a field appends a real column to the entity table', function () {
    [$entity, $card] = installedEntityWithCard();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Cognome',
        'column_name' => 'cognome',
        'type' => 'string',
    ]);

    $response->assertRedirect(route('admin.entities.builder.edit', $entity));
    expect(Schema::hasColumn('entity_contatti', 'cognome'))->toBeTrue();

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'cognome');
    expect($field)->not->toBeNull();
    expect($field->is_locked)->toBeFalse();
});

test('a select field stores its parsed options', function () {
    [$entity, $card] = installedEntityWithCard();

    $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Stato',
        'column_name' => 'stato',
        'type' => 'select',
        'options' => "open:Aperto\nclosed:Chiuso",
    ]);

    $field = $entity->fresh()->allFields()->firstWhere('column_name', 'stato');
    expect($field->options)->toBe(['open' => 'Aperto', 'closed' => 'Chiuso']);
});

test('a relation field adds an _id column', function () {
    [$entity, $card] = installedEntityWithCard();

    $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Responsabile',
        'column_name' => 'responsabile',
        'type' => 'relation',
        'relation_target' => 'model:App\\Models\\User',
    ]);

    expect(Schema::hasColumn('entity_contatti', 'responsabile_id'))->toBeTrue();
});

test('cannot add a field to an entity that is not installed', function () {
    $entity = Entity::create(['name' => 'Bozza', 'slug' => 'bozza', 'table_name' => 'entity_bozza']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Card', 'position' => 0]);

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Nome',
        'column_name' => 'nome',
        'type' => 'string',
    ]);

    $response->assertSessionHasErrors('entity_card_id');
    expect($entity->fresh()->allFields())->toHaveCount(0);
});

test('a reserved column name is rejected', function () {
    [$entity, $card] = installedEntityWithCard();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Utente',
        'column_name' => 'user_id',
        'type' => 'string',
    ]);

    $response->assertSessionHasErrors('column_name');
});

test('a duplicate column name is rejected', function () {
    [$entity, $card] = installedEntityWithCard();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Nome duplicato',
        'column_name' => 'nome',
        'type' => 'string',
    ]);

    $response->assertSessionHasErrors('column_name');
});

test('a select field without options is rejected', function () {
    [$entity, $card] = installedEntityWithCard();

    $response = $this->actingAs(adminUser())->post(route('admin.entities.fields.store', $entity), [
        'entity_card_id' => $card->id,
        'name' => 'Stato',
        'column_name' => 'stato',
        'type' => 'select',
    ]);

    $response->assertSessionHasErrors('options');
});
