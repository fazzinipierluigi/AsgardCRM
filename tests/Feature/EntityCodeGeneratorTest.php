<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityField;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Services\EntityCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function codeFieldCard(): EntityCard
{
    $entity = Entity::create(['name' => 'Fatture '.uniqid(), 'slug' => 'fatture-'.uniqid(), 'table_name' => 'entity_fatture_'.uniqid()]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);

    return EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
}

function codeField(array $overrides = []): EntityField
{
    return codeFieldCard()->fields()->create(array_merge([
        'name' => 'Codice', 'column_name' => 'codice', 'type' => EntityFieldType::Code,
        'options' => ['prefix' => 'INV-'], 'position' => 0,
    ], $overrides));
}

test('the first generated value uses sequence 1', function () {
    $field = codeField();

    expect(app(EntityCodeGenerator::class)->nextValue($field))->toBe('INV-1');
});

test('the sequence increments on every call and persists', function () {
    $field = codeField();
    $generator = app(EntityCodeGenerator::class);

    expect($generator->nextValue($field))->toBe('INV-1');
    expect($generator->nextValue($field))->toBe('INV-2');
    expect($generator->nextValue($field->fresh()))->toBe('INV-3');
    expect($field->fresh()->sequence)->toBe(4);
});

test('a field with no prefix generates a bare number', function () {
    $field = codeField(['options' => null]);

    expect(app(EntityCodeGenerator::class)->nextValue($field))->toBe('1');
});

test('two different code fields have independent sequences', function () {
    $card = codeFieldCard();
    $fieldA = $card->fields()->create(['name' => 'A', 'column_name' => 'codice_a', 'type' => EntityFieldType::Code, 'options' => ['prefix' => 'A-'], 'position' => 0]);
    $fieldB = $card->fields()->create(['name' => 'B', 'column_name' => 'codice_b', 'type' => EntityFieldType::Code, 'options' => ['prefix' => 'B-'], 'position' => 1]);
    $generator = app(EntityCodeGenerator::class);

    $generator->nextValue($fieldA);
    $generator->nextValue($fieldA);

    expect($generator->nextValue($fieldB))->toBe('B-1');
});
