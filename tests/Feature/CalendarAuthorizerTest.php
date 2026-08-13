<?php

use Fazzinipierluigi\AsgardCRM\Database\Seeders\CalendarEntitySeeder;
use Fazzinipierluigi\AsgardCRM\Enums\EntityVisibilityLevel;
use Fazzinipierluigi\AsgardCRM\Models\CalendarShare;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Models\EntityRoleVisibility;
use Fazzinipierluigi\AsgardCRM\Services\CalendarAuthorizer;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function calendarEntityForAuthorizer(): Entity
{
    test()->seed(CalendarEntitySeeder::class);

    return Entity::where('slug', 'calendario')->firstOrFail();
}

test('a user can always view/edit/delete their own events', function () {
    $entity = calendarEntityForAuthorizer();
    $user = User::factory()->create();
    $authorizer = app(CalendarAuthorizer::class);

    expect($authorizer->canView($user, $entity, $user->id))->toBeTrue();
    expect($authorizer->canEdit($user, $entity, $user->id))->toBeTrue();
    expect($authorizer->canDelete($user, $entity, $user->id))->toBeTrue();
});

test('without a share or role visibility, a user cannot see another user events', function () {
    $entity = calendarEntityForAuthorizer();
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $authorizer = app(CalendarAuthorizer::class);

    expect($authorizer->canView($other, $entity, $owner->id))->toBeFalse();
    expect($authorizer->canEdit($other, $entity, $owner->id))->toBeFalse();
    expect($authorizer->canDelete($other, $entity, $owner->id))->toBeFalse();
});

test('a view share grants viewing but not editing or deleting', function () {
    $entity = calendarEntityForAuthorizer();
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    CalendarShare::create(['owner_user_id' => $owner->id, 'shared_with_user_id' => $viewer->id, 'permission' => 'view']);
    $authorizer = app(CalendarAuthorizer::class);

    expect($authorizer->canView($viewer, $entity, $owner->id))->toBeTrue();
    expect($authorizer->canEdit($viewer, $entity, $owner->id))->toBeFalse();
    expect($authorizer->canDelete($viewer, $entity, $owner->id))->toBeFalse();
});

test('an edit share grants viewing, editing, and deleting', function () {
    $entity = calendarEntityForAuthorizer();
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    CalendarShare::create(['owner_user_id' => $owner->id, 'shared_with_user_id' => $editor->id, 'permission' => 'edit']);
    $authorizer = app(CalendarAuthorizer::class);

    expect($authorizer->canView($editor, $entity, $owner->id))->toBeTrue();
    expect($authorizer->canEdit($editor, $entity, $owner->id))->toBeTrue();
    expect($authorizer->canDelete($editor, $entity, $owner->id))->toBeTrue();
});

test('a role visibility level beyond OwnOnly already grants access without a share', function () {
    $entity = calendarEntityForAuthorizer();
    $owner = User::factory()->create();
    $manager = User::factory()->create();
    $role = Role::create(['name' => 'Manager', 'slug' => 'manager-'.uniqid()]);
    EntityRoleVisibility::create(['entity_id' => $entity->id, 'role_id' => $role->id, 'level' => EntityVisibilityLevel::Full]);
    $manager->assignRole($role);
    $authorizer = app(CalendarAuthorizer::class);

    expect($authorizer->canView($manager, $entity, $owner->id))->toBeTrue();
    expect($authorizer->canEdit($manager, $entity, $owner->id))->toBeTrue();
    expect($authorizer->canDelete($manager, $entity, $owner->id))->toBeTrue();
});

test('scopeQuery restricts to own and shared owners when role visibility is OwnOnly', function () {
    $entity = calendarEntityForAuthorizer();
    $viewer = User::factory()->create();
    $sharedOwner = User::factory()->create();
    $strangerOwner = User::factory()->create();
    CalendarShare::create(['owner_user_id' => $sharedOwner->id, 'shared_with_user_id' => $viewer->id, 'permission' => 'view']);

    $authorizer = app(CalendarAuthorizer::class);
    $query = EntityRecord::forEntity($entity)->newQuery();
    $authorizer->scopeQuery($query, $viewer, $entity);

    $wheres = collect($query->getQuery()->wheres)->firstWhere('column', 'user_id');
    expect($wheres['values'])->toEqualCanonicalizing([$sharedOwner->id, $viewer->id]);
});
