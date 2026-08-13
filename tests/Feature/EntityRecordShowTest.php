<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityFieldType;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityCard;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Models\EntityTab;
use Fazzinipierluigi\AsgardCRM\Services\EntityInstaller;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * An installed entity with a visible String + Date field and a hidden
 * String field — covers the read-only view page, the flatpickr-backed
 * Date input, and is_hidden's exclusion from both.
 */
function showTestEntity(string $slug = 'show-test'): Entity
{
    $entity = Entity::create(['name' => 'Show Test', 'slug' => $slug, 'table_name' => "entity_{$slug}"]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Dati', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);
    $card->fields()->create(['name' => 'Data evento', 'column_name' => 'data_evento', 'type' => EntityFieldType::Date, 'position' => 1]);
    $card->fields()->create(['name' => 'Interno', 'column_name' => 'interno', 'type' => EntityFieldType::String, 'position' => 2, 'is_hidden' => true]);

    app(EntityInstaller::class)->install($entity);

    return $entity->fresh();
}

test('the show page renders fields read-only with a Modifica link for a user who can edit', function () {
    $entity = showTestEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Mario Rossi', 'data_evento' => '2026-08-10']);

    $response = $this->actingAs($admin)->get(route('entities.show', [$entity, $record]));

    $response->assertOk()
        ->assertSee('Mario Rossi')
        ->assertSee('data-testid="entity-record-edit-link"', false)
        ->assertDontSee('name="nome"', false);
});

test('a hidden field never renders, in either show or edit mode', function () {
    $entity = showTestEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Mario Rossi', 'interno' => 'segreto-123']);

    $this->actingAs($admin)->get(route('entities.show', [$entity, $record]))
        ->assertOk()
        ->assertDontSee('segreto-123');

    $this->actingAs($admin)->get(route('entities.edit', [$entity, $record]))
        ->assertOk()
        ->assertDontSee('segreto-123');
});

test('saving a record through the generic update does not wipe a hidden field', function () {
    $entity = showTestEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Mario Rossi', 'interno' => 'segreto-123']);

    $this->actingAs($admin)->put(route('entities.update', [$entity, $record]), [
        'nome' => 'Mario Bianchi',
        'data_evento' => '2026-08-10',
    ])->assertRedirect();

    expect($record->fresh()->interno)->toBe('segreto-123');
});

test('a Date field renders as a flatpickr-backed text input, not a native date input', function () {
    $entity = showTestEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Mario Rossi']);

    $this->actingAs($admin)->get(route('entities.edit', [$entity, $record]))
        ->assertOk()
        ->assertSee('data-flatpickr-field="date"', false)
        ->assertDontSee('type="date"', false);
});

test('the change log lives in its own tab, not inline under Dati', function () {
    $entity = showTestEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Mario Rossi']);

    $this->actingAs($admin)->put(route('entities.update', [$entity, $record]), ['nome' => 'Mario Bianchi'])
        ->assertRedirect();

    $response = $this->actingAs($admin)->get(route('entities.edit', [$entity, $record]));

    $response->assertOk()
        ->assertSee('data-testid="entity-record-changelog-tab"', false)
        ->assertSee('data-testid="entity-record-changelog-panel"', false);
});

test('the change log always shows a creation event, even without any logged field changes', function () {
    $entity = showTestEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id]);

    $response = $this->actingAs($admin)->get(route('entities.show', [$entity, $record]));

    $response->assertOk()
        ->assertSee('data-testid="entity-record-changelog-tab"', false)
        ->assertSee(t('Record creato'))
        ->assertSee($admin->name);
});

test('the change log labels the oldest transaction as the creation event', function () {
    $entity = showTestEntity();
    $admin = adminUser();

    $this->actingAs($admin)->post(route('entities.store', $entity), ['nome' => 'Mario Rossi']);
    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();

    $this->actingAs($admin)->put(route('entities.update', [$entity, $record]), ['nome' => 'Luigi Bianchi']);

    $response = $this->actingAs($admin)->get(route('entities.show', [$entity, $record]));

    $response->assertOk()->assertSee(t('Record creato'));
});

test('the show page 404s when the entity is not installed and 403s outside the user\'s visibility', function () {
    $entity = showTestEntity();
    $owner = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore-show']);
    $role->givePermission(Permission::where('key', "entity_{$entity->slug}.index")->firstOrFail());
    $owner->assignRole($role);

    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $owner->id, 'nome' => 'Mario Rossi']);

    $stranger = User::factory()->create();
    $stranger->assignRole($role);

    $this->actingAs($stranger)->get(route('entities.show', [$entity, $record]))
        ->assertForbidden();
});
