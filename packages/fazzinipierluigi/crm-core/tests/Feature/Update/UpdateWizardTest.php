<?php

use Fazzinipierluigi\CrmCore\Models\Setting;
use Fazzinipierluigi\CrmCore\Models\VersionHistory;
use Fazzinipierluigi\CrmCore\Services\DependencyInstaller;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Never actually shell out to composer/npm from a test.
 */
beforeEach(function () {
    app()->instance(DependencyInstaller::class, new class extends DependencyInstaller
    {
        public function install(): void {}
    });
});

test('upgrading bumps the stored version and records a new version_history row', function () {
    Setting::setValue(null, 'app_version', '0.9.0');
    VersionHistory::create(['version' => '0.9.0', 'migrations_batch' => (int) DB::table('migrations')->max('batch')]);

    $this->get(route('update.welcome'))->assertOk();

    $this->post(route('update.run'))->assertRedirect(route('dashboard'));

    expect(Setting::valueFor(null, 'app_version'))->toBe(config('app.version'))
        ->and(VersionHistory::where('version', config('app.version'))->exists())->toBeTrue();
});

test('downgrading to a version this database never recorded fails cleanly', function () {
    Setting::setValue(null, 'app_version', '2.0.0');
    VersionHistory::create(['version' => '2.0.0', 'migrations_batch' => (int) DB::table('migrations')->max('batch')]);

    $this->get(route('update.welcome'))->assertOk();

    $this->post(route('update.run'))
        ->assertRedirect(route('update.welcome'))
        ->assertSessionHasErrors('update');

    expect(Setting::valueFor(null, 'app_version'))->toBe('2.0.0');
});

test('downgrading to a recorded version rolls back exactly the computed number of batches', function () {
    $currentBatch = (int) DB::table('migrations')->max('batch');

    Setting::setValue(null, 'app_version', '2.0.0');
    VersionHistory::create(['version' => '2.0.0', 'migrations_batch' => $currentBatch]);
    VersionHistory::create(['version' => config('app.version'), 'migrations_batch' => $currentBatch - 1]);

    // Artisan::shouldReceive() can't mock the Facade under Testbench —
    // its bound Orchestra\Testbench\Console\Kernel is `final`, and
    // Mockery's partial-mock-via-Facade mechanism needs to subclass it.
    // Mock the interface Artisan::call() itself delegates to instead
    // (Illuminate\Contracts\Console\Kernel::call()) — an interface has
    // no such restriction. A full replacement isn't safe though:
    // Testbench's own test teardown (InteractsWithMigrations, see
    // tests/TestCase.php) also calls ->call('migrate:rollback', [...])
    // through the very same Kernel to roll back this package's own
    // migration paths, with different arguments — a strict mock with
    // only the one expected call fails on that later unrelated call.
    // Delegate anything that isn't the specific call under test to the
    // real kernel instead of replacing it outright.
    $realKernel = app(Kernel::class);

    $kernel = Mockery::mock(Kernel::class);
    $kernel->shouldReceive('call')
        ->once()
        ->with('migrate:rollback', ['--step' => 1, '--force' => true])
        ->andReturn(0);
    $kernel->shouldReceive('call')
        ->andReturnUsing(fn (string $command, array $parameters = []) => $realKernel->call($command, $parameters));
    app()->instance(Kernel::class, $kernel);

    $this->post(route('update.run'))->assertRedirect(route('dashboard'));

    expect(Setting::valueFor(null, 'app_version'))->toBe(config('app.version'));
});
