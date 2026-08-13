<?php

use Fazzinipierluigi\AsgardCRM\Database\Seeders\EmailEntitySeeder;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('seeds the system email entity with its five locked fields', function () {
    $this->seed(EmailEntitySeeder::class);

    $entity = Entity::where('slug', 'email')->first();

    expect($entity)->not->toBeNull();
    expect($entity->is_system)->toBeTrue();
    expect($entity->is_email)->toBeTrue();
    expect($entity->is_installed)->toBeTrue();

    $columns = ['oggetto', 'mittente', 'destinatari', 'data_messaggio', 'ha_allegati', 'mail_account_id', 'folder', 'message_uid', 'uid_validity', 'message_id'];
    expect(Schema::hasColumns('entity_email', $columns))->toBeTrue();

    $fields = $entity->allFields();
    expect($fields)->toHaveCount(5);
    expect($fields->every(fn ($field) => $field->is_locked))->toBeTrue();
    expect($fields->first()->column_name)->toBe('oggetto');
    expect($fields->first()->type->value)->toBe('string');
});

test('seeds the five email CRUD permissions', function () {
    $this->seed(EmailEntitySeeder::class);

    expect(Permission::where('key', 'entity_email.index')->exists())->toBeTrue();
    expect(Permission::where('key', 'entity_email.create')->exists())->toBeTrue();
    expect(Permission::where('key', 'entity_email.edit')->exists())->toBeTrue();
    expect(Permission::where('key', 'entity_email.delete')->exists())->toBeTrue();
});

test('running the seeder twice does not duplicate the entity', function () {
    $this->seed(EmailEntitySeeder::class);
    $this->seed(EmailEntitySeeder::class);

    expect(Entity::where('slug', 'email')->count())->toBe(1);
});
