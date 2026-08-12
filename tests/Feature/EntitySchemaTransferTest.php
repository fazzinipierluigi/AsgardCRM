<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use Fazzinipierluigi\CrmCore\Services\EntitySchemaTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function exportableEntity(): Entity
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti', 'icon' => 'ti ti-users']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create([
        'name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'required' => true, 'position' => 0,
    ]);
    $card->fields()->create([
        'name' => 'Stato', 'column_name' => 'stato', 'type' => EntityFieldType::Select,
        'options' => ['open' => 'Aperto', 'closed' => 'Chiuso'], 'position' => 1,
    ]);

    return $entity;
}

test('export produces the expected schema shape', function () {
    $entity = exportableEntity();

    $data = app(EntitySchemaTransfer::class)->export($entity);

    expect($data['name'])->toBe('Contatti');
    expect($data['icon'])->toBe('ti ti-users');
    expect($data['tabs'][0]['name'])->toBe('Generale');
    expect($data['tabs'][0]['cards'][0]['name'])->toBe('Anagrafica');
    expect($data['tabs'][0]['cards'][0]['fields'])->toHaveCount(2);
    expect($data['tabs'][0]['cards'][0]['fields'][1]['options'])->toBe(['open' => 'Aperto', 'closed' => 'Chiuso']);
});

test('importing an exported schema recreates an equivalent, uninstalled custom entity', function () {
    $original = exportableEntity();
    $data = app(EntitySchemaTransfer::class)->export($original);

    $imported = app(EntitySchemaTransfer::class)->import($data);
    $imported->load('tabs.cards.fields');

    expect($imported->id)->not->toBe($original->id);
    expect($imported->name)->toBe('Contatti');
    expect($imported->slug)->not->toBe('contatti'); // uniqueSlug avoids the collision
    expect($imported->is_system)->toBeFalse();
    expect($imported->is_installed)->toBeFalse();
    expect($imported->tabs->first()->cards->first()->fields->pluck('column_name')->all())->toBe(['nome', 'stato']);
});

test('importing rejects a structure with no tabs', function () {
    expect(fn () => app(EntitySchemaTransfer::class)->import(['name' => 'Vuota', 'tabs' => []]))
        ->toThrow(RuntimeException::class);

    expect(Entity::where('name', 'Vuota')->exists())->toBeFalse();
});

test('importing rejects a reserved column name', function () {
    $data = [
        'name' => 'Contatti',
        'tabs' => [[
            'name' => 'Generale',
            'cards' => [[
                'name' => 'Anagrafica',
                'fields' => [['name' => 'Proprietario', 'column_name' => 'user_id', 'type' => 'string']],
            ]],
        ]],
    ];

    expect(fn () => app(EntitySchemaTransfer::class)->import($data))->toThrow(RuntimeException::class);
});

test('importing rejects an unknown field type', function () {
    $data = [
        'name' => 'Contatti',
        'tabs' => [[
            'name' => 'Generale',
            'cards' => [[
                'name' => 'Anagrafica',
                'fields' => [['name' => 'Boh', 'column_name' => 'boh', 'type' => 'not-a-type']],
            ]],
        ]],
    ];

    expect(fn () => app(EntitySchemaTransfer::class)->import($data))->toThrow(RuntimeException::class);
});
