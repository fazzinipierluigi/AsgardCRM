<?php

use Fazzinipierluigi\CrmCore\Models\Setting;
use App\Models\VersionHistory;
use App\Services\DependencyInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

    Artisan::shouldReceive('call')
        ->once()
        ->with('migrate:rollback', ['--step' => 1, '--force' => true]);

    $this->post(route('update.run'))->assertRedirect(route('dashboard'));

    expect(Setting::valueFor(null, 'app_version'))->toBe(config('app.version'));
});
