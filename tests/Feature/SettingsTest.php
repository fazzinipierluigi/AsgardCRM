<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('settings screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings');

    $response->assertStatus(200);
});

test('guests cannot access settings', function () {
    $response = $this->get('/settings');

    $response->assertRedirect('/login');
});

test('user can update name and email', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings', [
        'name' => 'New Name',
        'email' => 'new-email@example.com',
    ]);

    $response->assertRedirect();
    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new-email@example.com');
});

test('user can update password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/settings', [
        'name' => $user->name,
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $user->refresh();
    expect(Hash::check('new-password', $user->password))->toBeTrue();
});

test('password is left unchanged when field is empty', function () {
    $user = User::factory()->create([
        'password' => bcrypt('original-password'),
    ]);

    $this->actingAs($user)->put('/settings', [
        'name' => $user->name,
        'email' => $user->email,
    ]);

    $user->refresh();
    expect(Hash::check('original-password', $user->password))->toBeTrue();
});

test('email must be unique', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings', [
        'name' => $user->name,
        'email' => 'taken@example.com',
    ]);

    $response->assertSessionHasErrors('email');
});

test('user can update preferences', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings/preferences', [
        'date_format' => 'Y-m-d',
        'language' => 'en',
        'number_format' => 'en',
        'theme' => 'dark',
    ]);

    $response->assertRedirect();
    expect($user->getSetting('date_format'))->toBe('Y-m-d');
    expect($user->getSetting('language'))->toBe('en');
    expect($user->getSetting('number_format'))->toBe('en');
    expect($user->getSetting('theme'))->toBe('dark');
});

test('preferences reject values outside the allowed options', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings/preferences', [
        'date_format' => 'not-a-real-format',
        'language' => 'en',
        'number_format' => 'en',
        'theme' => 'dark',
    ]);

    $response->assertSessionHasErrors('date_format');
});

test('theme preference is reflected on the page', function () {
    $user = User::factory()->create();
    $user->setSetting('theme', 'dark');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertSee('data-bs-theme="dark"', false);
});
