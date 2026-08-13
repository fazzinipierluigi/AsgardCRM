<?php

use Fazzinipierluigi\CrmCore\Http\Middleware\EnsureAppIsInstalled;
use Fazzinipierluigi\CrmCore\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * EnsureAppIsInstalled is only registered as a middleware alias by
 * CrmServiceProvider (crm.installed), never auto-applied to the 'web'
 * group — a host wires it into its own bootstrap/app.php. These tests
 * exercise the middleware directly against ad-hoc routes instead.
 */
beforeEach(function () {
    @unlink(storage_path('installed'));

    Route::get('/__mw_plain', fn () => 'ok')->middleware(EnsureAppIsInstalled::class);
    Route::get('/__mw_install', fn () => 'ok')->middleware(EnsureAppIsInstalled::class)->name('install.fake');
});

afterEach(function () {
    @unlink(storage_path('installed'));
});

test('redirects a normal route to the installer when nothing is installed yet', function () {
    $this->get('/__mw_plain')->assertRedirect(route('install.welcome'));
});

test('lets an install.* route through when nothing is installed yet', function () {
    $this->get('/__mw_install')->assertOk();
});

test('self-heals when the database already has users but no marker file exists', function () {
    User::factory()->create();

    expect(file_exists(storage_path('installed')))->toBeFalse();

    $this->get('/__mw_plain')->assertOk();

    expect(file_exists(storage_path('installed')))->toBeTrue();
});

test('lets a normal route through once the marker file exists', function () {
    file_put_contents(storage_path('installed'), 'x');

    $this->get('/__mw_plain')->assertOk();
});

test('redirects away from an install.* route once the marker file exists', function () {
    file_put_contents(storage_path('installed'), 'x');

    $this->get('/__mw_install')->assertRedirect('/');
});

test('the health check route is never redirected', function () {
    $this->get('/up')->assertOk();
});
