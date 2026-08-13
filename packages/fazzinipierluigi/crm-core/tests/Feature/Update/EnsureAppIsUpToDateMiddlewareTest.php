<?php

use Fazzinipierluigi\CrmCore\Http\Middleware\EnsureAppIsUpToDate;
use Fazzinipierluigi\CrmCore\Models\Setting;
use Fazzinipierluigi\CrmCore\Models\VersionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Global registration of EnsureAppIsUpToDate is a host bootstrap/app.php
 * decision (crm.up-to-date alias, see CrmServiceProvider) — exercise it
 * directly against ad-hoc routes instead.
 */
beforeEach(function () {
    Route::get('/__mw_plain', fn () => 'ok')->middleware(EnsureAppIsUpToDate::class);
    Route::get('/__mw_update', fn () => 'ok')->middleware(EnsureAppIsUpToDate::class)->name('update.fake');
});

test('self-heals when no app_version setting exists yet', function () {
    expect(Setting::valueFor(null, 'app_version'))->toBeNull();

    $this->get('/__mw_plain')->assertOk();

    expect(Setting::valueFor(null, 'app_version'))->toBe(config('app.version'))
        ->and(VersionHistory::where('version', config('app.version'))->exists())->toBeTrue();
});

test('lets a normal route through when the versions match', function () {
    Setting::setValue(null, 'app_version', config('app.version'));

    $this->get('/__mw_plain')->assertOk();
});

test('redirects a normal route to the update wizard when the versions differ', function () {
    Setting::setValue(null, 'app_version', '0.0.1');

    $this->get('/__mw_plain')->assertRedirect(route('update.welcome'));
});

test('lets an update.* route through even when the versions differ', function () {
    Setting::setValue(null, 'app_version', '0.0.1');

    $this->get('/__mw_update')->assertOk();
});

test('the health check route is never redirected', function () {
    Setting::setValue(null, 'app_version', '0.0.1');

    $this->get('/up')->assertOk();
});
