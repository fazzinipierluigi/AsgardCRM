<?php

namespace Fazzinipierluigi\AsgardCRM\Services;

use Fazzinipierluigi\AsgardCRM\Contracts\CrmUser;
use Fazzinipierluigi\AsgardCRM\Database\Seeders\CalendarEntitySeeder;
use Fazzinipierluigi\AsgardCRM\Database\Seeders\DocumentsEntitySeeder;
use Fazzinipierluigi\AsgardCRM\Database\Seeders\EmailEntitySeeder;
use Fazzinipierluigi\AsgardCRM\Database\Seeders\LanguageSeeder;
use Fazzinipierluigi\AsgardCRM\Database\Seeders\TranslationSeeder;
use Fazzinipierluigi\AsgardCRM\Models\LoginProvider;
use Fazzinipierluigi\AsgardCRM\Models\Setting;
use Fazzinipierluigi\AsgardCRM\Models\VersionHistory;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Drives the first-run installation wizard's actual work: verifying a
 * database connection, writing it to .env, running migrations, seeding
 * the base data, and creating the first administrator. Reuses
 * DynamicDatabaseConnector (already used by the SQL importer/workflow
 * "assign variable from SQL" features) to probe credentials without
 * touching the app's real default connection.
 */
class ApplicationInstaller
{
    public function __construct(
        private readonly EnvFileWriter $envWriter,
        private readonly DynamicDatabaseConnector $connector,
    ) {}

    /**
     * @param  array<string, mixed>  $dbConfig
     *
     * @throws RuntimeException if the connection cannot be established
     */
    public function testConnection(array $dbConfig): void
    {
        $this->prepareSqliteFile($dbConfig);

        try {
            $this->connector->run($dbConfig, 'install_test', fn ($connection) => $connection->getPdo());
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @param  array<string, mixed>  $dbConfig
     * @param  array<string, mixed>  $adminData
     */
    public function install(array $dbConfig, array $adminData): CrmUser
    {
        $driver = $dbConfig['driver'];

        $this->prepareSqliteFile($dbConfig);
        $this->writeEnvironment($dbConfig);
        $this->reconfigureConnection($driver, $dbConfig);

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => LanguageSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => TranslationSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => CalendarEntitySeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => DocumentsEntitySeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => EmailEntitySeeder::class, '--force' => true]);

        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'is_admin' => true, 'is_system' => true]
        );

        LoginProvider::firstOrCreate(
            ['slug' => 'local'],
            ['name' => 'Locale', 'type' => 'local', 'is_active' => true, 'is_system' => true]
        );

        $userModel = config('crm.user_model');

        $user = $userModel::create([
            'name' => $adminData['name'],
            'username' => $adminData['username'],
            'email' => $adminData['email'],
            'password' => $adminData['password'],
        ]);

        $user->assignRole($role);

        Setting::setValue(null, 'app_version', config('app.version'));

        VersionHistory::create([
            'version' => config('app.version'),
            'migrations_batch' => (int) DB::table('migrations')->max('batch'),
        ]);

        file_put_contents(storage_path('installed'), now()->toIso8601String());

        return $user;
    }

    /**
     * Laravel's own SqliteConnector refuses to connect to a non-existent
     * database file (throws SQLiteDatabaseDoesNotExistException) rather
     * than creating it, unlike a raw PDO sqlite connection.
     *
     * @param  array<string, mixed>  $dbConfig
     */
    private function prepareSqliteFile(array $dbConfig): void
    {
        if ($dbConfig['driver'] !== 'sqlite' || $dbConfig['database'] === ':memory:') {
            return;
        }

        if (! file_exists($dbConfig['database'])) {
            if (! is_dir(dirname($dbConfig['database']))) {
                mkdir(dirname($dbConfig['database']), 0755, true);
            }

            touch($dbConfig['database']);
        }
    }

    /**
     * @param  array<string, mixed>  $dbConfig
     */
    private function writeEnvironment(array $dbConfig): void
    {
        $values = [
            'DB_CONNECTION' => $dbConfig['driver'],
            'DB_DATABASE' => $dbConfig['database'],
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
        ];

        if (empty(config('app.key'))) {
            $values['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        }

        if ($dbConfig['driver'] !== 'sqlite') {
            $values['DB_HOST'] = (string) $dbConfig['host'];
            $values['DB_PORT'] = (string) $dbConfig['port'];
            $values['DB_USERNAME'] = (string) $dbConfig['username'];
            $values['DB_PASSWORD'] = (string) ($dbConfig['password'] ?? '');
        }

        $this->envWriter->set($values);

        if (isset($values['APP_KEY'])) {
            config(['app.key' => $values['APP_KEY']]);
        }
    }

    /**
     * @param  array<string, mixed>  $dbConfig
     */
    private function reconfigureConnection(string $driver, array $dbConfig): void
    {
        $values = ["database.connections.{$driver}.database" => $dbConfig['database']];

        // Setting host/port/username/password at all (even to null) makes
        // Laravel's ConnectionFactory take the host-resolution path, which
        // fails for a driverless-host connection like sqlite — only set
        // them for drivers that actually need them.
        if ($driver !== 'sqlite') {
            $values["database.connections.{$driver}.host"] = $dbConfig['host'];
            $values["database.connections.{$driver}.port"] = $dbConfig['port'];
            $values["database.connections.{$driver}.username"] = $dbConfig['username'];
            $values["database.connections.{$driver}.password"] = $dbConfig['password'] ?? '';
        }

        $values['database.default'] = $driver;

        config($values);

        DB::purge($driver);
    }
}
