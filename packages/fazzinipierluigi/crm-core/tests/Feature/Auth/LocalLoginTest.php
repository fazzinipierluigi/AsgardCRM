<?php

use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using username and password', function () {
    $user = User::factory()->create([
        'username' => 'jdoe',
        'password' => bcrypt('password'),
    ]);

    $response = $this->post('/login', [
        'username' => 'jdoe',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard'));
});

test('users cannot authenticate with invalid password', function () {
    $user = User::factory()->create([
        'username' => 'jdoe',
    ]);

    $this->post('/login', [
        'username' => 'jdoe',
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});
