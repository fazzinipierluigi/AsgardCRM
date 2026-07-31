<?php

use App\Models\Entity;
use Database\Seeders\DocumentsEntitySeeder;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('seeds the system documents entity with its two locked fields and upload columns', function () {
    $this->seed(DocumentsEntitySeeder::class);

    $entity = Entity::where('slug', 'documenti')->first();

    expect($entity)->not->toBeNull();
    expect($entity->is_system)->toBeTrue();
    expect($entity->is_documents)->toBeTrue();
    expect($entity->is_installed)->toBeTrue();

    $columns = ['nome', 'descrizione', 'folder_id', 'original_filename', 'stored_path', 'mime_type', 'file_size'];
    expect(Schema::hasColumns('entity_documenti', $columns))->toBeTrue();

    $fields = $entity->allFields();
    expect($fields)->toHaveCount(2);
    expect($fields->every(fn ($field) => $field->is_locked))->toBeTrue();
});

test('seeds the documents CRUD permissions', function () {
    $this->seed(DocumentsEntitySeeder::class);

    expect(Permission::where('key', 'entity_documenti.index')->exists())->toBeTrue();
    expect(Permission::where('key', 'entity_documenti.create')->exists())->toBeTrue();
    expect(Permission::where('key', 'entity_documenti.edit')->exists())->toBeTrue();
    expect(Permission::where('key', 'entity_documenti.delete')->exists())->toBeTrue();
});

test('running the seeder twice does not duplicate the entity', function () {
    $this->seed(DocumentsEntitySeeder::class);
    $this->seed(DocumentsEntitySeeder::class);

    expect(Entity::where('slug', 'documenti')->count())->toBe(1);
});
