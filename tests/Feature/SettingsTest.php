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
    seedLanguages();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings/preferences', [
        'date_format' => 'Y-m-d',
        'language' => 'en',
        'number_format' => 'en',
        'theme' => 'dark',
        'theme_base' => 'slate',
        'theme_color' => 'azure',
    ]);

    $response->assertRedirect();
    expect($user->getSetting('date_format'))->toBe('Y-m-d');
    expect($user->getSetting('language'))->toBe('en');
    expect($user->getSetting('number_format'))->toBe('en');
    expect($user->getSetting('theme'))->toBe('dark');
    expect($user->getSetting('theme_base'))->toBe('slate');
    expect($user->getSetting('theme_color'))->toBe('azure');
});

test('preferences reject values outside the allowed options', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings/preferences', [
        'date_format' => 'not-a-real-format',
        'language' => 'en',
        'number_format' => 'en',
        'theme' => 'dark',
        'theme_base' => 'slate',
        'theme_color' => 'azure',
    ]);

    $response->assertSessionHasErrors('date_format');
});

test('preferences reject a theme base or color outside the allowed options', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put('/settings/preferences', [
        'date_format' => 'd/m/Y',
        'language' => 'en',
        'number_format' => 'en',
        'theme' => 'dark',
        'theme_base' => 'not-a-real-base',
        'theme_color' => 'not-a-real-color',
    ]);

    $response->assertSessionHasErrors(['theme_base', 'theme_color']);
});

test('theme preference is reflected on the page', function () {
    $user = User::factory()->create();
    $user->setSetting('theme', 'dark');
    $user->setSetting('theme_base', 'slate');
    $user->setSetting('theme_color', 'azure');

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertSee('data-bs-theme="dark"', false);
    $response->assertSee('data-bs-theme-base="slate"', false);
    $response->assertSee('data-bs-theme-primary="azure"', false);
});
