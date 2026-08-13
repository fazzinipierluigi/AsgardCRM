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

function trashEntity(string $slug = 'contatti-cestino', ?string $name = null): Entity
{
    $entity = Entity::create(['name' => $name ?? 'Contatti', 'slug' => $slug, 'table_name' => "entity_{$slug}"]);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'position' => 0]);

    app(EntityInstaller::class)->install($entity);

    return $entity;
}

/**
 * Just A Gate's trash.* permissions are never pre-created (same
 * CLI-only pattern as admin.access) — tests create the row directly,
 * same as an operator would via `permission:create`.
 */
function grantTrashPermission(Role $role, string $key): void
{
    $permission = Permission::firstOrCreate(['key' => $key], ['name' => $key]);
    $role->givePermission($permission);
}

test('destroying a record soft-deletes it: gone from the records list, still in the database', function () {
    $entity = trashEntity();
    $user = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'nome' => 'Mario Rossi']);

    $this->actingAs($user)->delete(route('entities.destroy', [$entity, $record]))
        ->assertRedirect(route('entities.index', $entity));

    expect(EntityRecord::forEntity($entity)->newQuery()->find($record->id))->toBeNull();
    expect(EntityRecord::forEntity($entity)->newQuery()->withTrashed()->find($record->id))->not->toBeNull();

    $response = $this->actingAs($user)->getJson(route('entities.data', ['entity' => $entity, 'start' => 0, 'limit' => 25]));
    expect(collect($response->json('data')))->toHaveCount(0);
});

test('a user without trash.show cannot open the trash index', function () {
    $entity = trashEntity();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('trash.index'))->assertForbidden();
});

test('an admin can open the trash index and see a soft-deleted record for a permitted entity', function () {
    $entity = trashEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Mario Rossi']);
    $record->delete();

    $this->actingAs($admin)->get(route('trash.index'))->assertOk()->assertSee($entity->name);

    $response = $this->actingAs($admin)->getJson(route('trash.data', ['entity' => $entity, 'start' => 0, 'limit' => 25]));
    $response->assertOk();
    expect(collect($response->json('data')))->toHaveCount(1);
});

test('restoring a trashed record brings it back into the normal listing', function () {
    $entity = trashEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Mario Rossi']);
    $record->delete();

    $this->actingAs($admin)->post(route('trash.restore', [$entity, $record]))
        ->assertRedirect(route('trash.index', ['entity' => $entity->slug]));

    expect(EntityRecord::forEntity($entity)->newQuery()->find($record->id))->not->toBeNull();
});

test('force-deleting a trashed record removes it permanently', function () {
    $entity = trashEntity();
    $admin = adminUser();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'Mario Rossi']);
    $record->delete();

    $this->actingAs($admin)->delete(route('trash.force-delete', [$entity, $record]))
        ->assertRedirect(route('trash.index', ['entity' => $entity->slug]));

    expect(EntityRecord::forEntity($entity)->newQuery()->withTrashed()->find($record->id))->toBeNull();
});

test('emptying an entity\'s trash force-deletes every trashed record for it', function () {
    $entity = trashEntity();
    $admin = adminUser();
    $recordA = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'A']);
    $recordB = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'B']);
    $recordA->delete();
    $recordB->delete();

    $this->actingAs($admin)->delete(route('trash.empty-entity', $entity))
        ->assertRedirect(route('trash.index', ['entity' => $entity->slug]));

    expect(EntityRecord::forEntity($entity)->newQuery()->withTrashed()->count())->toBe(0);
});

test('emptying the whole trash force-deletes trashed records across every entity', function () {
    $entityA = trashEntity('contatti-cestino-a');
    $entityB = trashEntity('contatti-cestino-b');
    $admin = adminUser();
    $recordA = EntityRecord::forEntity($entityA)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'A']);
    $recordB = EntityRecord::forEntity($entityB)->newQuery()->create(['user_id' => $admin->id, 'nome' => 'B']);
    $recordA->delete();
    $recordB->delete();

    $this->actingAs($admin)->delete(route('trash.empty-all'))->assertRedirect(route('trash.index'));

    expect(EntityRecord::forEntity($entityA)->newQuery()->withTrashed()->count())->toBe(0);
    expect(EntityRecord::forEntity($entityB)->newQuery()->withTrashed()->count())->toBe(0);
});

test('a non-admin needs both trash.restore and the entity\'s delete permission to restore a record', function () {
    $entity = trashEntity();
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);
    $user->assignRole($role);

    // OwnOnly is the default visibility level for a role with no explicit
    // EntityRoleVisibility row, so the record must belong to $user itself
    // for EntityRecordAuthorizer::canDelete() to allow it once the flat
    // entity_{slug}.delete permission is granted below.
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $user->id, 'nome' => 'Mario Rossi']);
    $record->delete();

    // actingAs() reuses the very same $user PHP object across requests
    // within a test, and Just A Gate's Authorizable trait caches the
    // resolved permission list on that object after its first can()
    // call — so each step below re-fetches a fresh instance, exactly
    // as a real new HTTP request would.
    $this->actingAs($user)->post(route('trash.restore', [$entity, $record]))->assertForbidden();

    grantTrashPermission($role, 'trash.restore');
    $this->actingAs($user->fresh())->post(route('trash.restore', [$entity, $record]))->assertForbidden();

    $role->givePermission(Permission::where('key', "entity_{$entity->slug}.delete")->firstOrFail());
    $this->actingAs($user->fresh())->post(route('trash.restore', [$entity, $record]))
        ->assertRedirect(route('trash.index', ['entity' => $entity->slug]));
});

test('an entity the user cannot delete is excluded from the trash entity picker', function () {
    $visible = trashEntity('contatti-visibile', 'Contatti Visibili');
    $hidden = trashEntity('contatti-nascosta', 'Contatti Nascosti');

    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);
    grantTrashPermission($role, 'trash.show');
    $role->givePermission(Permission::where('key', "entity_{$visible->slug}.delete")->firstOrFail());
    $user->assignRole($role);

    $response = $this->actingAs($user)->get(route('trash.index'));

    $response->assertOk()->assertSee($visible->name)->assertDontSee($hidden->name);
});
