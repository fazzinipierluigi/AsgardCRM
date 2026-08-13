<?php

use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;

/**
 * `migrate:fresh` (what ResetInstallCommand calls) drops every table
 * and, on sqlite, VACUUMs — which SQLite refuses inside a transaction.
 * RefreshDatabase wraps every test in one on the shared 'testing'
 * :memory: connection, so this needs its own connection outside that
 * wrapping, same as InstallWizardTest/UpdateWizardTest.
 */
beforeEach(function () {
    $this->tempDbPath = sys_get_temp_dir().'/'.uniqid('reset_install_db_', true).'.sqlite';
    touch($this->tempDbPath);

    config(['database.connections.sqlite.database' => $this->tempDbPath, 'database.default' => 'sqlite']);
    DB::purge('sqlite');

    $this->artisan('migrate', ['--force' => true]);
});

afterEach(function () {
    @unlink(storage_path('installed'));

    config(['database.default' => 'testing']);
    DB::purge('sqlite');
    @unlink($this->tempDbPath);
});

test('wipes the database and removes the installed marker', function () {
    file_put_contents(storage_path('installed'), 'x');
    User::factory()->create();

    $this->artisan('app:reset-install', ['--force' => true])->assertSuccessful();

    expect(file_exists(storage_path('installed')))->toBeFalse()
        ->and(DB::connection('sqlite')->table('users')->count())->toBe(0);
});
