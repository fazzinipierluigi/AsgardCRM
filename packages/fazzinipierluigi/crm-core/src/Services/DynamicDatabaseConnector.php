<?php

namespace Fazzinipierluigi\CrmCore\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;

/**
 * Registers a uniquely-named, throwaway Laravel database connection from
 * admin-stored credentials (driver/host/port/database/username/password),
 * for querying an external database the app has no static config/*
 * connection for. Extracted from Importers\Channels\DatabaseImporterChannel
 * (the original, still the reference for the config shape) so the same
 * credential-handling code isn't duplicated by the workflow "Assegna
 * variabile da SQL" action — one implementation to get right, not two.
 *
 * Callers are responsible for calling DB::purge() themselves if they need
 * the connection to outlive a single synchronous call (see fetch()'s
 * generator in DatabaseImporterChannel, which can't use run() below since
 * its body doesn't execute until iterated, well after run() would have
 * already purged the connection).
 */
class DynamicDatabaseConnector
{
    /**
     * @param  array<string, mixed>  $config
     * @return array{0: string, 1: Connection}
     */
    public function connect(array $config, string $namePrefix): array
    {
        $name = $namePrefix.'_'.Str::random(12);

        $connection = [
            'driver' => $this->laravelDriverFor((string) ($config['driver'] ?? '')),
            'database' => $config['database'] ?? null,
            'username' => $config['username'] ?? null,
            'password' => $config['password'] ?? null,
            'charset' => 'utf8mb4',
            'options' => [PDO::ATTR_TIMEOUT => 10],
        ];

        // Only set "host" when one is actually configured: its mere presence
        // (even as null) makes Laravel's ConnectionFactory take the
        // host-resolution path, which fails hosts-array-is-empty for
        // driverless-host connections like sqlite.
        if (! empty($config['host'])) {
            $connection['host'] = $config['host'];
            $connection['port'] = $config['port'] ?? null;
        }

        config(["database.connections.{$name}" => $connection]);

        return [$name, DB::connection($name)];
    }

    /**
     * Convenience wrapper for synchronous (non-generator) callers: opens
     * the connection, runs the callback, always purges afterwards.
     *
     * @param  array<string, mixed>  $config
     */
    public function run(array $config, string $namePrefix, callable $callback): mixed
    {
        [$name, $connection] = $this->connect($config, $namePrefix);

        try {
            return $callback($connection);
        } finally {
            DB::purge($name);
        }
    }

    /**
     * Maps the driver value chosen in the wizard/admin form to the driver
     * name Laravel's connection factory understands. Falls through
     * unknown values unchanged so tests can point this at sqlite.
     */
    private function laravelDriverFor(string $driver): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => 'mysql',
            'pgsql', 'postgres', 'postgresql' => 'pgsql',
            'sqlsrv', 'sqlserver' => 'sqlsrv',
            default => $driver,
        };
    }
}
