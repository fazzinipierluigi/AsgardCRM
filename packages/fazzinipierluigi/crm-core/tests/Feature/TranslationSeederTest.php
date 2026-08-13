<?php

use Fazzinipierluigi\AsgardCRM\Database\Seeders\TranslationSeeder;
use Fazzinipierluigi\AsgardCRM\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('imports both languages for every string', function () {
    $this->seed(TranslationSeeder::class);

    expect(Translation::where('key', 'Dashboard')->where('language', 'it')->value('value'))->toBe('Dashboard');
    expect(Translation::where('key', 'Dashboard')->where('language', 'en')->value('value'))->toBe('Dashboard');
    expect(Translation::where('key', 'Utenti')->where('language', 'en')->value('value'))->toBe('Users');
    expect(t('Utenti', [], 'en'))->toBe('Users');
});

test('re-running the seeder does not duplicate rows', function () {
    $this->seed(TranslationSeeder::class);
    $countAfterFirstRun = Translation::count();

    $this->seed(TranslationSeeder::class);

    expect(Translation::count())->toBe($countAfterFirstRun);
});

test('re-running the seeder keeps values up to date', function () {
    $this->seed(TranslationSeeder::class);

    Translation::where('key', 'Dashboard')->where('language', 'en')->update(['value' => 'Tampered']);

    $this->seed(TranslationSeeder::class);

    expect(Translation::where('key', 'Dashboard')->where('language', 'en')->value('value'))->toBe('Dashboard');
});
