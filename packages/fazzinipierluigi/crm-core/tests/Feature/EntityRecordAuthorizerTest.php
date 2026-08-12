<?php

use Fazzinipierluigi\CrmCore\Enums\EntityVisibilityLevel;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityRoleVisibility;
use Fazzinipierluigi\CrmCore\Services\EntityRecordAuthorizer;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Fazzinipierluigi\CrmCore\Tests\Fixtures\User;

uses(RefreshDatabase::class);

function entityForVisibility(): Entity
{
    return Entity::create(['name' => 'Contatti', 'slug' => 'contatti', 'table_name' => 'entity_contatti']);
}

function userWithLevel(Entity $entity, ?EntityVisibilityLevel $level): User
{
    $user = User::factory()->create();

    if ($level !== null) {
        $role = Role::create(['name' => 'Operatore '.uniqid(), 'slug' => 'operatore-'.uniqid()]);
        EntityRoleVisibility::create(['entity_id' => $entity->id, 'role_id' => $role->id, 'level' => $level]);
        $user->assignRole($role);
    }

    return $user;
}

test('an admin always gets full access regardless of any configured level', function () {
    $entity = entityForVisibility();
    $admin = adminUser();

    $authorizer = app(EntityRecordAuthorizer::class);

    expect($authorizer->levelFor($admin, $entity))->toBe(EntityVisibilityLevel::Full);
});

test('a role with no configured level defaults to own only', function () {
    $entity = entityForVisibility();
    $user = userWithLevel($entity, null);
    Role::create(['name' => 'Senza livello', 'slug' => 'senza-livello']); // unrelated role, no visibility row
    $user->assignRole(Role::where('slug', 'senza-livello')->firstOrFail());

    expect(app(EntityRecordAuthorizer::class)->levelFor($user, $entity))->toBe(EntityVisibilityLevel::OwnOnly);
});

test('the most permissive role wins when a user has several', function () {
    $entity = entityForVisibility();
    $user = User::factory()->create();

    $readOnly = Role::create(['name' => 'Lettore', 'slug' => 'lettore']);
    EntityRoleVisibility::create(['entity_id' => $entity->id, 'role_id' => $readOnly->id, 'level' => EntityVisibilityLevel::OwnOnly]);

    $full = Role::create(['name' => 'Potente', 'slug' => 'potente']);
    EntityRoleVisibility::create(['entity_id' => $entity->id, 'role_id' => $full->id, 'level' => EntityVisibilityLevel::Full]);

    $user->assignRole($readOnly, $full);

    expect(app(EntityRecordAuthorizer::class)->levelFor($user, $entity))->toBe(EntityVisibilityLevel::Full);
});

test('visibility levels grant the expected view/edit/delete rights', function (EntityVisibilityLevel $level, bool $canViewOthers, bool $canEditOthers, bool $canDeleteOthers) {
    $entity = entityForVisibility();
    $user = userWithLevel($entity, $level);
    $owner = User::factory()->create();

    $authorizer = app(EntityRecordAuthorizer::class);

    // Always full rights over one's own records, at every level.
    expect($authorizer->canView($user, $entity, $user->id))->toBeTrue();
    expect($authorizer->canEdit($user, $entity, $user->id))->toBeTrue();
    expect($authorizer->canDelete($user, $entity, $user->id))->toBeTrue();

    expect($authorizer->canView($user, $entity, $owner->id))->toBe($canViewOthers);
    expect($authorizer->canEdit($user, $entity, $owner->id))->toBe($canEditOthers);
    expect($authorizer->canDelete($user, $entity, $owner->id))->toBe($canDeleteOthers);
})->with([
    'own only' => [EntityVisibilityLevel::OwnOnly, false, false, false],
    'own manage, others read' => [EntityVisibilityLevel::OwnManageOthersRead, true, false, false],
    'own manage, others edit' => [EntityVisibilityLevel::OwnManageOthersEdit, true, true, false],
    'full' => [EntityVisibilityLevel::Full, true, true, true],
]);

test('scopeQuery restricts the query to own records only at the own-only level', function () {
    $entity = entityForVisibility();
    $user = userWithLevel($entity, EntityVisibilityLevel::OwnOnly);

    $query = EntityRecord::forEntity($entity)->newQuery();
    app(EntityRecordAuthorizer::class)->scopeQuery($query, $user, $entity);

    expect($query->toSql())->toContain('"user_id" = ?');
});

test('scopeQuery does not restrict the query above own-only', function () {
    $entity = entityForVisibility();
    $user = userWithLevel($entity, EntityVisibilityLevel::OwnManageOthersRead);

    $query = EntityRecord::forEntity($entity)->newQuery();
    app(EntityRecordAuthorizer::class)->scopeQuery($query, $user, $entity);

    expect($query->toSql())->not->toContain('user_id');
});
