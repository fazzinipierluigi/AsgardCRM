<?php

use Fazzinipierluigi\CrmCore\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('returns the key itself when no translation exists', function () {
    expect(t('missing.key'))->toBe('missing.key');
});

test('returns the translation for an explicit locale', function () {
    Translation::create(['key' => 'greeting', 'language' => 'en', 'value' => 'Hello']);

    expect(t('greeting', [], 'en'))->toBe('Hello');
});

test('uses the authenticated user language preference when no locale is given', function () {
    Translation::create(['key' => 'greeting', 'language' => 'en', 'value' => 'Hello']);
    $user = User::factory()->create();
    $user->setSetting('language', 'en');
    $this->actingAs($user);

    expect(t('greeting'))->toBe('Hello');
});

test('falls back to the app locale when the user language has no translation', function () {
    config(['app.locale' => 'it']);
    Translation::create(['key' => 'greeting', 'language' => 'it', 'value' => 'Ciao']);
    $user = User::factory()->create();
    $user->setSetting('language', 'en');
    $this->actingAs($user);

    expect(t('greeting'))->toBe('Ciao');
});

test('guests use the app locale', function () {
    config(['app.locale' => 'it']);
    Translation::create(['key' => 'greeting', 'language' => 'it', 'value' => 'Ciao']);

    expect(t('greeting'))->toBe('Ciao');
});

test('replaces placeholders like trans() does', function () {
    Translation::create(['key' => 'welcome', 'language' => 'en', 'value' => 'Welcome, :name']);

    expect(t('welcome', ['name' => 'Jane'], 'en'))->toBe('Welcome, Jane');
});

test('newly saved translations are visible without a fresh request', function () {
    expect(t('greeting', [], 'en'))->toBe('greeting');

    Translation::create(['key' => 'greeting', 'language' => 'en', 'value' => 'Hello']);

    expect(t('greeting', [], 'en'))->toBe('Hello');
});
