<?php

use App\Models\User;
use Laravel\Dusk\Browser;

test('user can login with username and password', function () {
    $user = User::factory()->create([
        'username' => 'jdoe',
        'password' => bcrypt('password'),
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/login')
            ->type('username', 'jdoe')
            ->type('password', 'password')
            ->press('Sign in')
            ->waitForLocation('/dashboard')
            ->waitForText('Welcome, '.$user->name);
    });
});

test('user sees error with invalid credentials', function () {
    User::factory()->create([
        'username' => 'jdoe',
        'password' => bcrypt('password'),
    ]);

    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->type('username', 'jdoe')
            ->type('password', 'wrong-password')
            ->press('Sign in')
            ->assertPathIs('/login')
            ->waitForText('These credentials do not match our records.');
    });
});

test('user can logout', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/dashboard')
            ->click('[data-testid="user-menu-toggle"]')
            ->waitFor('[data-testid="user-menu-toggle"] + .dropdown-menu.show')
            ->press('Logout')
            ->waitForLocation('/login');
    });
});
