<?php

use Fazzinipierluigi\CrmCore\Services\EnvFileWriter;
use Illuminate\Support\Facades\DB;

/**
 * No RefreshDatabase here on purpose: the happy-path test below points
 * the real `sqlite` connection at its own temp file (exactly what
 * ApplicationInstaller does against a freshly chosen database), separate
 * from the shared :memory: connection the rest of the suite uses.
 * afterEach restores database.default back to 'testing' — it must NOT
 * touch the 'testing' connection itself (no config reset, no
 * DB::purge('testing')): purging a live :memory: connection destroys
 * its schema outright (sqlite ':memory:' dies with its PDO handle), and
 * database.connections.testing.database was never changed to begin with
 * (ApplicationInstaller::reconfigureConnection() only ever touches the
 * chosen driver's own connection config, here 'sqlite') — every test
 * after the first one in this file failed with "no such table: ..."
 * once that unnecessary purge silently wiped the shared in-memory
 * schema TestCase::defineEnvironment() set up for the whole suite.
 */
beforeEach(function () {
    @unlink(storage_path('installed'));

    $this->tempDbPath = sys_get_temp_dir().'/'.uniqid('install_db_', true).'.sqlite';
    $this->tempEnvPath = sys_get_temp_dir().'/'.uniqid('install_env_', true).'.env';
    file_put_contents($this->tempEnvPath, "APP_KEY=\n");

    app()->instance(EnvFileWriter::class, new EnvFileWriter($this->tempEnvPath));
});

afterEach(function () {
    @unlink(storage_path('installed'));
    @unlink($this->tempDbPath);
    @unlink($this->tempEnvPath);

    config(['database.default' => 'testing']);
    DB::purge('sqlite');
});

test('hitting the admin step before completing the database step redirects back', function () {
    $this->get(route('install.admin'))->assertRedirect(route('install.database'));
});

test('hitting the finish step before completing prior steps redirects back', function () {
    $this->get(route('install.finish'))->assertRedirect(route('install.database'));
});

test('rejects an unsupported database driver', function () {
    $this->post(route('install.database.store'), ['driver' => 'oracle', 'database' => 'x'])
        ->assertSessionHasErrors('driver');
});

test('rejects a non-sqlite driver missing connection fields', function () {
    $this->post(route('install.database.store'), ['driver' => 'mysql', 'database' => 'x'])
        ->assertSessionHasErrors(['host', 'username']);
});

test('admin step requires matching password confirmation', function () {
    session(['install.database' => ['driver' => 'sqlite', 'database' => $this->tempDbPath]]);

    $this->post(route('install.admin.store'), [
        'name' => 'Admin',
        'username' => 'admin',
        'email' => 'admin@example.com',
        'password' => 'secret1234',
        'password_confirmation' => 'not-matching',
    ])->assertSessionHasErrors('password');
});

test('completes the full wizard against sqlite and lands authenticated on the dashboard', function () {
    $this->post(route('install.database.store'), [
        'driver' => 'sqlite',
        'database' => $this->tempDbPath,
    ])->assertRedirect(route('install.admin'));

    $this->post(route('install.admin.store'), [
        'name' => 'Admin',
        'username' => 'admin',
        'email' => 'admin@example.com',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ])->assertRedirect(route('install.finish'));

    $this->get(route('install.finish'))->assertOk();

    $this->post(route('install.run'))->assertRedirect(route('dashboard'));

    expect(file_exists(storage_path('installed')))->toBeTrue()
        ->and(file_get_contents($this->tempEnvPath))->toContain('DB_CONNECTION=sqlite');

    $user = DB::connection('sqlite')->table('users')->where('username', 'admin')->first();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('admin@example.com');

    $this->assertAuthenticated();
});
