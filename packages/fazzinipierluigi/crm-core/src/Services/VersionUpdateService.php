<?php

namespace Fazzinipierluigi\AsgardCRM\Services;

use Fazzinipierluigi\AsgardCRM\Models\Setting;
use Fazzinipierluigi\AsgardCRM\Models\VersionHistory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Drives the update wizard: compares the database's recorded app_version
 * Setting against the deployed code's config('app.version') and, in
 * whichever direction they differ, refreshes dependencies, migrates (or
 * rolls back) the database, and runs the registered VersionUpgradeRunner
 * steps for the versions in between.
 */
class VersionUpdateService
{
    public function __construct(
        private readonly DependencyInstaller $dependencyInstaller,
        private readonly VersionUpgradeRunner $upgradeRunner,
    ) {}

    /**
     * Read-only summary for the wizard's welcome page.
     *
     * @return array{from: string, to: string, direction: string, downgradeBlocked: bool, pendingMigrations: int|null}
     */
    public function plan(): array
    {
        $from = $this->currentVersion();
        $to = (string) config('app.version');

        $direction = match (true) {
            version_compare($from, $to, '<') => 'upgrade',
            version_compare($from, $to, '>') => 'downgrade',
            default => 'none',
        };

        return [
            'from' => $from,
            'to' => $to,
            'direction' => $direction,
            'downgradeBlocked' => $direction === 'downgrade' && ! VersionHistory::where('version', $to)->exists(),
            'pendingMigrations' => $this->pendingMigrationsCount(),
        ];
    }

    /**
     * @throws RuntimeException if a downgrade target was never actually recorded
     */
    public function run(): void
    {
        $from = $this->currentVersion();
        $to = (string) config('app.version');

        if (version_compare($from, $to, '=')) {
            return;
        }

        $downgrading = version_compare($from, $to, '>');

        $target = null;
        if ($downgrading) {
            $target = VersionHistory::where('version', $to)->first();

            if (! $target) {
                throw new RuntimeException(
                    "Cannot automatically roll back to version {$to}: this database was never recorded at that version. Restore a backup instead."
                );
            }
        }

        $this->dependencyInstaller->install();

        if ($downgrading) {
            $currentBatch = (int) DB::table('migrations')->max('batch');
            $steps = $currentBatch - $target->migrations_batch;

            if ($steps > 0) {
                Artisan::call('migrate:rollback', ['--step' => $steps, '--force' => true]);
            }

            $this->upgradeRunner->downgrade($from, $to);
        } else {
            Artisan::call('migrate', ['--force' => true]);
            $this->upgradeRunner->upgrade($from, $to);
        }

        Setting::setValue(null, 'app_version', $to);

        VersionHistory::create([
            'version' => $to,
            'migrations_batch' => (int) DB::table('migrations')->max('batch'),
        ]);
    }

    private function currentVersion(): string
    {
        return (string) Setting::valueFor(null, 'app_version', config('app.version'));
    }

    /**
     * Best-effort, cosmetic count of this app's own pending migrations
     * (excludes vendor package migrations) — never blocks the wizard if
     * it can't be computed.
     */
    private function pendingMigrationsCount(): ?int
    {
        try {
            $ran = collect(app('migration.repository')->getRan());

            $all = collect(glob(database_path('migrations/*.php')))
                ->map(fn (string $path) => basename($path, '.php'));

            return $all->diff($ran)->count();
        } catch (Throwable) {
            return null;
        }
    }
}
