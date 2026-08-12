<?php

use Fazzinipierluigi\CrmCore\Models\Entity;
use Database\Seeders\CalendarEntitySeeder;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('seeds the system calendar entity with its six locked fields', function () {
    $this->seed(CalendarEntitySeeder::class);

    $entity = Entity::where('slug', 'calendario')->first();

    expect($entity)->not->toBeNull();
    expect($entity->is_system)->toBeTrue();
    expect($entity->is_calendar)->toBeTrue();
    expect($entity->is_installed)->toBeTrue();

    $columns = ['title', 'description', 'show_as', 'status', 'start_datetime', 'end_datetime', 'relatable_type', 'relatable_id'];
    expect(Schema::hasColumns('entity_calendario', $columns))->toBeTrue();

    $fields = $entity->allFields();
    expect($fields)->toHaveCount(6);
    expect($fields->every(fn ($field) => $field->is_locked))->toBeTrue();
});

test('seeds the four calendar CRUD permissions', function () {
    $this->seed(CalendarEntitySeeder::class);

    expect(Permission::where('key', 'entity_calendario.index')->exists())->toBeTrue();
    expect(Permission::where('key', 'entity_calendario.create')->exists())->toBeTrue();
    expect(Permission::where('key', 'entity_calendario.edit')->exists())->toBeTrue();
    expect(Permission::where('key', 'entity_calendario.delete')->exists())->toBeTrue();
});

test('running the seeder twice does not duplicate the entity', function () {
    $this->seed(CalendarEntitySeeder::class);
    $this->seed(CalendarEntitySeeder::class);

    expect(Entity::where('slug', 'calendario')->count())->toBe(1);
});
