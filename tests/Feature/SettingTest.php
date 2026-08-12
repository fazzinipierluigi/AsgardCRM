<?php

use Fazzinipierluigi\CrmCore\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('falls back to the given default when nothing is set', function () {
    $user = User::factory()->create();

    expect($user->getSetting('theme', 'light'))->toBe('light');
});

test('falls back to the global value when the user has none', function () {
    $user = User::factory()->create();

    Setting::setValue(null, 'theme', 'dark');

    expect($user->getSetting('theme', 'light'))->toBe('dark');
});

test('a user-specific value takes priority over the global value', function () {
    $user = User::factory()->create();

    Setting::setValue(null, 'theme', 'dark');
    $user->setSetting('theme', 'light');

    expect($user->getSetting('theme'))->toBe('light');
});

test('setting a value twice updates it instead of duplicating the row', function () {
    $user = User::factory()->create();

    $user->setSetting('theme', 'dark');
    $user->setSetting('theme', 'light');

    expect(Setting::where('user_id', $user->id)->where('key', 'theme')->count())->toBe(1);
    expect($user->getSetting('theme'))->toBe('light');
});

test('settings are scoped per user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $userA->setSetting('theme', 'dark');

    expect($userA->getSetting('theme'))->toBe('dark');
    expect($userB->getSetting('theme', 'light'))->toBe('light');
});

test('deleting a user cascades its settings', function () {
    $user = User::factory()->create();
    $user->setSetting('theme', 'dark');

    $userId = $user->id;
    $user->delete();

    expect(Setting::where('user_id', $userId)->exists())->toBeFalse();
});
