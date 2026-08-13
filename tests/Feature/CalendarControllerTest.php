<?php

use Fazzinipierluigi\AsgardCRM\Database\Seeders\CalendarEntitySeeder;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\EntityRecord;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function calendarEntity(): Entity
{
    test()->seed(CalendarEntitySeeder::class);

    return Entity::where('slug', 'calendario')->firstOrFail();
}

function userWithCalendarPermissions(array $actions = ['index', 'create', 'edit', 'delete']): User
{
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore-'.uniqid()]);

    foreach ($actions as $action) {
        $role->givePermission(Permission::where('key', "entity_calendario.{$action}")->firstOrFail());
    }

    $user->assignRole($role);

    return $user;
}

test('guests are redirected to login', function () {
    calendarEntity();

    $this->get(route('calendar.index'))->assertRedirect(route('login'));
});

test('a user without calendar permission is forbidden', function () {
    calendarEntity();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('calendar.index'))->assertForbidden();
});

test('a user with calendar permission can view the calendar page', function () {
    calendarEntity();
    $user = userWithCalendarPermissions(['index']);

    $this->actingAs($user)->get(route('calendar.index'))->assertOk();
});

// The 3 tests below check the sidebar/quick-access menu's own markup
// (host-owned layouts/base.blade.php, never shipped by this package —
// see tests/Feature/SidebarMenuTest.php's file-level skip for the same
// reasoning). Belongs in a real host's own test suite once one exists.

test('the calendar entity shows up in the sidebar menu, linking to the dedicated calendar page', function () {
    $entity = calendarEntity();
    $entity->update(['show_in_menu' => true]);
    $user = userWithCalendarPermissions(['index']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSee('data-testid="menu-entity-calendario"', false)
        ->assertSee(route('calendar.index'), false);
})->skip('Sidebar menu rendering is host-owned view logic, not shipped by this package.');

test('hiding the calendar entity from the main menu moves it into "Altre entità"', function () {
    $entity = calendarEntity();
    $entity->update(['show_in_menu' => false]);
    $user = userWithCalendarPermissions(['index']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSee('data-testid="menu-other-entities"', false)
        ->assertSee(route('calendar.index'), false);
})->skip('Sidebar menu rendering is host-owned view logic, not shipped by this package.');

test('the calendar entity in quick access opens the calendar page embedded', function () {
    $entity = calendarEntity();
    $entity->update(['show_in_quick_access' => true]);
    $user = userWithCalendarPermissions(['index']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSee('data-testid="quick-access-calendario"', false)
        ->assertSee(route('calendar.index', ['embed' => 1]), false);
})->skip('Sidebar menu rendering is host-owned view logic, not shipped by this package.');

test('embed=1 strips the sidebar chrome from the calendar page, same as any other entity', function () {
    calendarEntity();
    $user = userWithCalendarPermissions(['index']);

    $response = $this->actingAs($user)->get(route('calendar.index', ['embed' => 1]));

    $response->assertOk()->assertDontSee('data-testid="sidebar"', false);
});

test('events feed returns only events overlapping the requested range', function () {
    $entity = calendarEntity();
    $user = userWithCalendarPermissions();

    EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $user->id,
        'title' => 'In range',
        'show_as' => 'busy',
        'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00',
        'end_datetime' => '2026-08-10 11:00:00',
    ]);

    EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $user->id,
        'title' => 'Out of range',
        'show_as' => 'busy',
        'status' => 'confirmed',
        'start_datetime' => '2026-09-10 10:00:00',
        'end_datetime' => '2026-09-10 11:00:00',
    ]);

    $response = $this->actingAs($user)->getJson(route('calendar.events', ['start' => '2026-08-01', 'end' => '2026-08-31']));

    $response->assertOk();
    $titles = collect($response->json())->pluck('title');
    expect($titles)->toContain('In range');
    expect($titles)->not->toContain('Out of range');
});

test('a user can create an event', function () {
    calendarEntity();
    $user = userWithCalendarPermissions();

    $response = $this->actingAs($user)->postJson(route('calendar.events.store'), [
        'title' => 'Riunione',
        'description' => 'Nota',
        'show_as' => 'busy',
        'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00',
        'end_datetime' => '2026-08-10 11:00:00',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('title', 'Riunione');
    expect(EntityRecord::forEntity(Entity::where('slug', 'calendario')->first())->newQuery()->where('user_id', $user->id)->count())->toBe(1);
});

test('creating an event requires the fixed required fields', function () {
    calendarEntity();
    $user = userWithCalendarPermissions();

    $response = $this->actingAs($user)->postJson(route('calendar.events.store'), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['title', 'show_as', 'status', 'start_datetime', 'end_datetime']);
});

test('a user without the create permission cannot create an event', function () {
    calendarEntity();
    $user = userWithCalendarPermissions(['index']);

    $response = $this->actingAs($user)->postJson(route('calendar.events.store'), [
        'title' => 'Riunione',
        'show_as' => 'busy',
        'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00',
        'end_datetime' => '2026-08-10 11:00:00',
    ]);

    $response->assertForbidden();
});

test('a user can update their own event', function () {
    $entity = calendarEntity();
    $user = userWithCalendarPermissions();

    $record = EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $user->id,
        'title' => 'Originale',
        'show_as' => 'busy',
        'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00',
        'end_datetime' => '2026-08-10 11:00:00',
    ]);

    $response = $this->actingAs($user)->putJson(route('calendar.events.update', $record->id), [
        'title' => 'Aggiornato',
        'show_as' => 'available',
        'status' => 'tentative',
        'start_datetime' => '2026-08-10 12:00:00',
        'end_datetime' => '2026-08-10 13:00:00',
    ]);

    $response->assertOk()->assertJsonPath('title', 'Aggiornato');
});

test('a user without edit visibility on others records cannot update them', function () {
    $entity = calendarEntity();
    $owner = User::factory()->create();
    $other = userWithCalendarPermissions();

    $record = EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $owner->id,
        'title' => 'Di un altro',
        'show_as' => 'busy',
        'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00',
        'end_datetime' => '2026-08-10 11:00:00',
    ]);

    $response = $this->actingAs($other)->putJson(route('calendar.events.update', $record->id), [
        'title' => 'Tentativo',
        'show_as' => 'busy',
        'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00',
        'end_datetime' => '2026-08-10 11:00:00',
    ]);

    $response->assertForbidden();
});

test('a user can delete their own event', function () {
    $entity = calendarEntity();
    $user = userWithCalendarPermissions();

    $record = EntityRecord::forEntity($entity)->newQuery()->create([
        'user_id' => $user->id,
        'title' => 'Da eliminare',
        'show_as' => 'busy',
        'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00',
        'end_datetime' => '2026-08-10 11:00:00',
    ]);

    $response = $this->actingAs($user)->deleteJson(route('calendar.events.destroy', $record->id));

    $response->assertNoContent();
    expect(EntityRecord::forEntity($entity)->newQuery()->find($record->id))->toBeNull();
});

test('an event can be linked to a relatable user record', function () {
    $entity = calendarEntity();
    $user = userWithCalendarPermissions();
    $related = User::factory()->create(['name' => 'Referente']);

    $response = $this->actingAs($user)->postJson(route('calendar.events.store'), [
        'title' => 'Con relazione',
        'show_as' => 'busy',
        'status' => 'confirmed',
        'start_datetime' => '2026-08-10 10:00:00',
        'end_datetime' => '2026-08-10 11:00:00',
        'relatable_type' => 'model:App\\Models\\User',
        'relatable_id' => $related->id,
    ]);

    $response->assertCreated();
    $record = EntityRecord::forEntity($entity)->newQuery()->where('title', 'Con relazione')->firstOrFail();
    expect($record->relatable_type)->toBe('model:App\\Models\\User');
    expect((int) $record->relatable_id)->toBe($related->id);
});

test('relatables endpoint returns options for the requested target', function () {
    calendarEntity();
    $user = userWithCalendarPermissions();
    User::factory()->create(['name' => 'Alfa']);

    $response = $this->actingAs($user)->getJson(route('calendar.relatables', ['type' => 'model:'.User::class]));

    $response->assertOk();
    expect(collect($response->json())->pluck('label'))->toContain('Alfa');
});
