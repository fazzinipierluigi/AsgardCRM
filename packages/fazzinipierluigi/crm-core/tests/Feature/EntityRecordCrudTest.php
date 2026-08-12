<?php

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Enums\EntityVisibilityLevel;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityCard;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityRoleVisibility;
use Fazzinipierluigi\CrmCore\Models\EntityTab;
use Fazzinipierluigi\CrmCore\Services\EntityInstaller;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Fazzinipierluigi\CrmCore\Tests\Fixtures\User;

uses(RefreshDatabase::class);

function contactsEntity(): Entity
{
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $tab = EntityTab::create(['entity_id' => $entity->id, 'name' => 'Generale', 'position' => 0]);
    $card = EntityCard::create(['entity_tab_id' => $tab->id, 'name' => 'Anagrafica', 'position' => 0]);
    $card->fields()->create(['name' => 'Nome', 'column_name' => 'nome', 'type' => EntityFieldType::String, 'required' => true, 'position' => 0]);
    $card->fields()->create(['name' => 'VIP', 'column_name' => 'vip', 'type' => EntityFieldType::Checkbox, 'position' => 1]);
    $card->fields()->create([
        'name' => 'Stato', 'column_name' => 'stato', 'type' => EntityFieldType::Select,
        'options' => ['open' => 'Aperto', 'closed' => 'Chiuso'], 'position' => 2,
    ]);
    $card->fields()->create(['name' => 'Note', 'column_name' => 'note', 'type' => EntityFieldType::RichText, 'position' => 3]);

    app(EntityInstaller::class)->install($entity);

    return $entity;
}

function userWithEntityPermissions(Entity $entity, array $actions, ?EntityVisibilityLevel $level = null): User
{
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore '.uniqid(), 'slug' => 'operatore-'.uniqid()]);

    foreach ($actions as $action) {
        $role->givePermission(Permission::where('key', "entity_{$entity->slug}.{$action}")->firstOrFail());
    }

    if ($level !== null) {
        EntityRoleVisibility::create(['entity_id' => $entity->id, 'role_id' => $role->id, 'level' => $level]);
    }

    $user->assignRole($role);

    return $user;
}

// ── create/store ────────────────────────────────────────────────────────────

test('a user without the create permission cannot see the create form', function () {
    $entity = contactsEntity();
    $user = userWithEntityPermissions($entity, ['index']);

    $this->actingAs($user)->get(route('entities.create', $entity))->assertForbidden();
});

test('a user with the create permission can create a record', function () {
    $entity = contactsEntity();
    $user = userWithEntityPermissions($entity, ['index', 'create']);

    $response = $this->actingAs($user)->post(route('entities.store', $entity), [
        'nome' => 'Mario Rossi',
        'vip' => '1',
        'stato' => 'open',
        'note' => '<b>Ottimo</b> cliente <script>alert(1)</script>',
    ]);

    $response->assertRedirect(route('entities.index', $entity));
    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    expect($record->nome)->toBe('Mario Rossi');
    expect((bool) $record->vip)->toBeTrue();
    expect($record->stato)->toBe('open');
    expect($record->note)->toBe('<b>Ottimo</b> cliente alert(1)');
    expect($record->user_id)->toBe($user->id);
});

test('an unchecked checkbox is stored as false', function () {
    $entity = contactsEntity();
    $user = userWithEntityPermissions($entity, ['index', 'create']);

    $this->actingAs($user)->post(route('entities.store', $entity), ['nome' => 'Luigi Bianchi']);

    $record = EntityRecord::forEntity($entity)->newQuery()->firstOrFail();
    expect((bool) $record->vip)->toBeFalse();
});

test('a required field is validated', function () {
    $entity = contactsEntity();
    $user = userWithEntityPermissions($entity, ['index', 'create']);

    $response = $this->actingAs($user)->post(route('entities.store', $entity), []);

    $response->assertSessionHasErrors('nome');
});

test('a select field only accepts one of its defined options', function () {
    $entity = contactsEntity();
    $user = userWithEntityPermissions($entity, ['index', 'create']);

    $response = $this->actingAs($user)->post(route('entities.store', $entity), [
        'nome' => 'Mario',
        'stato' => 'not-an-option',
    ]);

    $response->assertSessionHasErrors('stato');
});

// ── edit/update ownership ───────────────────────────────────────────────────

test('the owner can always edit their own record regardless of level', function () {
    $entity = contactsEntity();
    $owner = userWithEntityPermissions($entity, ['index', 'edit'], EntityVisibilityLevel::OwnOnly);
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $owner->id, 'nome' => 'Mario']);

    $response = $this->actingAs($owner)->put(route('entities.update', [$entity, $record->id]), ['nome' => 'Mario Rossi']);

    $response->assertRedirect(route('entities.index', $entity));
    expect($record->fresh()->nome)->toBe('Mario Rossi');
});

test('own-only cannot edit another user\'s record', function () {
    $entity = contactsEntity();
    $owner = User::factory()->create();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $owner->id, 'nome' => 'Mario']);

    $editor = userWithEntityPermissions($entity, ['index', 'edit'], EntityVisibilityLevel::OwnOnly);

    $this->actingAs($editor)->get(route('entities.edit', [$entity, $record->id]))->assertForbidden();
    $this->actingAs($editor)->put(route('entities.update', [$entity, $record->id]), ['nome' => 'Hacked'])->assertForbidden();
    expect($record->fresh()->nome)->toBe('Mario');
});

test('own-manage-others-edit can edit another user\'s record', function () {
    $entity = contactsEntity();
    $owner = User::factory()->create();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $owner->id, 'nome' => 'Mario']);

    $editor = userWithEntityPermissions($entity, ['index', 'edit'], EntityVisibilityLevel::OwnManageOthersEdit);

    $response = $this->actingAs($editor)->put(route('entities.update', [$entity, $record->id]), ['nome' => 'Mario Rossi']);

    $response->assertRedirect(route('entities.index', $entity));
    expect($record->fresh()->nome)->toBe('Mario Rossi');
});

// ── destroy ownership ────────────────────────────────────────────────────────

test('own-manage-others-edit still cannot delete another user\'s record', function () {
    $entity = contactsEntity();
    $owner = User::factory()->create();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $owner->id, 'nome' => 'Mario']);

    $user = userWithEntityPermissions($entity, ['index', 'delete'], EntityVisibilityLevel::OwnManageOthersEdit);

    $this->actingAs($user)->delete(route('entities.destroy', [$entity, $record->id]))->assertForbidden();
    expect(EntityRecord::forEntity($entity)->newQuery()->find($record->id))->not->toBeNull();
});

test('full level can delete another user\'s record', function () {
    $entity = contactsEntity();
    $owner = User::factory()->create();
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $owner->id, 'nome' => 'Mario']);

    $user = userWithEntityPermissions($entity, ['index', 'delete'], EntityVisibilityLevel::Full);

    $response = $this->actingAs($user)->delete(route('entities.destroy', [$entity, $record->id]));

    $response->assertRedirect(route('entities.index', $entity));
    expect(EntityRecord::forEntity($entity)->newQuery()->find($record->id))->toBeNull();
});

test('the owner can always delete their own record', function () {
    $entity = contactsEntity();
    $owner = userWithEntityPermissions($entity, ['index', 'delete'], EntityVisibilityLevel::OwnOnly);
    $record = EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $owner->id, 'nome' => 'Mario']);

    $this->actingAs($owner)->delete(route('entities.destroy', [$entity, $record->id]));

    expect(EntityRecord::forEntity($entity)->newQuery()->find($record->id))->toBeNull();
});

// ── datatable row flags ──────────────────────────────────────────────────────

test('the records datatable exposes per-row can_edit/can_delete flags', function () {
    $entity = contactsEntity();
    $owner = User::factory()->create();
    EntityRecord::forEntity($entity)->newQuery()->create(['user_id' => $owner->id, 'nome' => 'Mario']);

    $user = userWithEntityPermissions($entity, ['index'], EntityVisibilityLevel::OwnManageOthersRead);

    $response = $this->actingAs($user)->getJson(route('entities.data', ['entity' => $entity, 'start' => 0, 'limit' => 25]));

    $row = collect($response->json('data'))->firstWhere('nome', 'Mario');
    expect($row['can_edit'])->toBeFalse();
    expect($row['can_delete'])->toBeFalse();
});
