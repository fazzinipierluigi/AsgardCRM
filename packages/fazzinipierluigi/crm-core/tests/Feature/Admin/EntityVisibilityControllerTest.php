<?php

use Fazzinipierluigi\AsgardCRM\Enums\EntityVisibilityLevel;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityRoleVisibility;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);

    $this->get(route('admin.entities.visibility.edit', $entity))->assertRedirect(route('login'));
});

test('admin can view the visibility matrix, excluding admin roles', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    Role::create(['name' => 'Operatore', 'slug' => 'operatore']);

    $response = $this->actingAs($admin)->get(route('admin.entities.visibility.edit', $entity));

    $response->assertOk();
    $response->assertSee('Operatore');
    $response->assertDontSee('data-testid="entity-visibility-row-admin"', false);
});

test('admin can set a visibility level for a role', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);

    $response = $this->actingAs($admin)->put(route('admin.entities.visibility.update', $entity), [
        'levels' => [$role->id => EntityVisibilityLevel::OwnManageOthersEdit->value],
    ]);

    $response->assertRedirect(route('admin.entities.index'));
    $visibility = EntityRoleVisibility::where('entity_id', $entity->id)->where('role_id', $role->id)->firstOrFail();
    expect($visibility->level)->toBe(EntityVisibilityLevel::OwnManageOthersEdit);
});

test('saving again updates the level instead of duplicating the row', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);

    $this->actingAs($admin)->put(route('admin.entities.visibility.update', $entity), [
        'levels' => [$role->id => EntityVisibilityLevel::OwnOnly->value],
    ]);
    $this->actingAs($admin)->put(route('admin.entities.visibility.update', $entity), [
        'levels' => [$role->id => EntityVisibilityLevel::Full->value],
    ]);

    expect(EntityRoleVisibility::where('entity_id', $entity->id)->where('role_id', $role->id)->count())->toBe(1);
    expect(EntityRoleVisibility::where('entity_id', $entity->id)->where('role_id', $role->id)->value('level'))->toBe(EntityVisibilityLevel::Full);
});

test('an invalid level is rejected', function () {
    $admin = adminUser();
    $entity = Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore']);

    $response = $this->actingAs($admin)->put(route('admin.entities.visibility.update', $entity), [
        'levels' => [$role->id => 'not-a-level'],
    ]);

    $response->assertSessionHasErrors("levels.{$role->id}");
});
