<?php

use App\Enums\EntityFieldType;
use App\Models\Entity;
use App\Models\EntityCard;
use App\Models\EntityTab;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('guests are redirected to login for both export and import', function () {
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $this->get(route('admin.entities.export', $entity))->assertRedirect(route('login'));
    $this->get(route('admin.entities.import.form'))->assertRedirect(route('login'));
});

test('admin can download an entity export as a JSON attachment', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    $response = $this->actingAs($admin)->get(route('admin.entities.export', $entity));

    $response->assertOk();
    $response->assertHeader('Content-Disposition', 'attachment; filename="entity-contatti.json"');
    expect($response->json('name'))->toBe('Contatti');
});

test('admin can import an uploaded schema file', function () {
    Storage::fake('local');
    $admin = adminUser();

    $schema = [
        'name' => 'Aziende',
        'icon' => null,
        'tabs' => [[
            'name' => 'Generale',
            'cards' => [[
                'name' => 'Dati',
                'fields' => [['name' => 'Ragione sociale', 'column_name' => 'ragione_sociale', 'type' => 'string', 'required' => true]],
            ]],
        ]],
    ];
    $file = UploadedFile::fake()->createWithContent('entity-aziende.json', json_encode($schema));

    $response = $this->actingAs($admin)->post(route('admin.entities.import'), ['file' => $file]);

    $entity = Entity::where('name', 'Aziende')->firstOrFail();
    $response->assertRedirect(route('admin.entities.builder.edit', $entity));
    expect($entity->allFields()->pluck('column_name')->all())->toBe(['ragione_sociale']);
});

test('importing a malformed JSON file shows an error', function () {
    Storage::fake('local');
    $admin = adminUser();
    $file = UploadedFile::fake()->createWithContent('bad.json', 'not json at all');

    $response = $this->actingAs($admin)->post(route('admin.entities.import'), ['file' => $file]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(Entity::count())->toBe(0);
});
