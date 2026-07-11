<?php

use App\Models\User;
use Fazzinipierluigi\JustAGate\Models\Role;
use Laravel\Dusk\Browser;

test('layout shows fixed sidebar and top navbar', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/dashboard')
            ->assertVisible('[data-testid="sidebar"]')
            ->assertVisible('[data-testid="topnavbar"]')
            ->assertVisible('[data-testid="menu-dashboard"]');
    });
});

test('user dropdown shows name and role and opens on click', function () {
    $role = Role::create(['name' => 'Administrator', 'slug' => 'admin', 'is_admin' => true, 'is_system' => true]);
    $user = User::factory()->create(['name' => 'Jane Doe']);
    $user->assignRole($role);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/dashboard')
            ->assertSeeIn('[data-testid="user-menu-name"]', 'Jane Doe')
            ->assertSeeIn('[data-testid="user-menu-role"]', 'Administrator')
            ->click('[data-testid="user-menu-toggle"]')
            ->waitFor('[data-testid="user-menu-toggle"] + .dropdown-menu.show')
            ->assertSee('Impostazioni')
            ->assertSee('Logout');
    });
});

test('administration link is visible to privileged users', function () {
    $role = Role::create(['name' => 'Administrator', 'slug' => 'admin', 'is_admin' => true, 'is_system' => true]);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/dashboard')
            ->assertVisible('[data-testid="menu-admin"]');
    });
});

test('administration link is hidden for users without privileges', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/dashboard')
            ->assertMissing('[data-testid="menu-admin"]');
    });
});
