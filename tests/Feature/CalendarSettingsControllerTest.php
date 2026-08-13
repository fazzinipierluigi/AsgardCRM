<?php

use Fazzinipierluigi\AsgardCRM\Database\Seeders\CalendarEntitySeeder;
use Fazzinipierluigi\AsgardCRM\Models\CalendarShare;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function calendarUser(): User
{
    test()->seed(CalendarEntitySeeder::class);

    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore-'.uniqid()]);
    $role->givePermission(Permission::where('key', 'entity_calendario.index')->firstOrFail());
    $user->assignRole($role);

    return $user;
}

test('guests are redirected to login', function () {
    Entity::where('slug', 'calendario')->exists() || test()->seed(CalendarEntitySeeder::class);

    $this->get(route('calendar.settings.edit'))->assertRedirect(route('login'));
});

test('a user without calendar access is forbidden', function () {
    test()->seed(CalendarEntitySeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('calendar.settings.edit'))->assertForbidden();
});

test('a user can view their calendar sharing settings', function () {
    $user = calendarUser();

    $this->actingAs($user)->get(route('calendar.settings.edit'))->assertOk();
});

test('a user can share their calendar with another user', function () {
    $user = calendarUser();
    $other = calendarUser();

    $response = $this->actingAs($user)->put(route('calendar.settings.shares.update'), [
        'shares' => [$other->id => 'view'],
    ]);

    $response->assertRedirect(route('calendar.settings.edit'));
    expect(CalendarShare::where('owner_user_id', $user->id)->where('shared_with_user_id', $other->id)->first()?->permission->value)->toBe('view');
});

test('setting a share to none removes it', function () {
    $user = calendarUser();
    $other = calendarUser();
    CalendarShare::create(['owner_user_id' => $user->id, 'shared_with_user_id' => $other->id, 'permission' => 'view']);

    $this->actingAs($user)->put(route('calendar.settings.shares.update'), [
        'shares' => [$other->id => 'none'],
    ]);

    expect(CalendarShare::where('owner_user_id', $user->id)->where('shared_with_user_id', $other->id)->exists())->toBeFalse();
});

test('a user cannot create a share on behalf of another owner', function () {
    $user = calendarUser();
    $other = calendarUser();
    $third = calendarUser();

    // Even if somehow submitted, only the authenticated user's own id is
    // ever used as owner_user_id — there's no owner_user_id input at all.
    $this->actingAs($user)->put(route('calendar.settings.shares.update'), [
        'shares' => [$third->id => 'edit'],
    ]);

    expect(CalendarShare::where('owner_user_id', $other->id)->exists())->toBeFalse();
    expect(CalendarShare::where('owner_user_id', $user->id)->where('shared_with_user_id', $third->id)->exists())->toBeTrue();
});

test('sharing with yourself is ignored', function () {
    $user = calendarUser();

    $this->actingAs($user)->put(route('calendar.settings.shares.update'), [
        'shares' => [$user->id => 'edit'],
    ]);

    expect(CalendarShare::where('owner_user_id', $user->id)->where('shared_with_user_id', $user->id)->exists())->toBeFalse();
});
