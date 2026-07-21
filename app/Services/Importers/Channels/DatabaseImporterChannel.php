<?php

namespace App\Services\Importers\Channels;

use App\Models\Importer;
use App\Services\Importers\ImporterChannelInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Throwable;

/**
 * Imports from an arbitrary external database via a raw query the admin
 * configured (no SELECT-only restriction — the query runs exactly as
 * entered, on a dynamic read connection scoped to this run).
 */
class DatabaseImporterChannel implements ImporterChannelInterface
{
    public function preview(Importer $importer): array
    {
        $config = $importer->config ?? [];

        try {
            [$name, $pdo] = $this->connect($config);

            try {
                $stmt = $pdo->query($config['query'] ?? '');

                if ($stmt === false) {
                    return ['ok' => true, 'columns' => [], 'sample' => []];
                }

                $sample = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                $columns = $sample !== null ? array_keys($sample) : $this->columnsFromEmptyResult($stmt);

                return ['ok' => true, 'columns' => $columns, 'sample' => $sample ?? []];
            } finally {
                DB::purge($name);
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetch(Importer $importer): iterable
    {
        $config = $importer->config ?? [];
        [$name, $pdo] = $this->connect($config);

        try {
            $stmt = $pdo->query($config['query'] ?? '');

            if ($stmt === false) {
                return;
            }

            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                yield $row;
            }
        } finally {
            DB::purge($name);
        }
    }

    /**
     * @return array<int, string>
     */
    private function columnsFromEmptyResult(\PDOStatement $stmt): array
    {
        $columns = [];

        try {
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $meta = $stmt->getColumnMeta($i);
                $columns[] = $meta['name'] ?? "col_{$i}";
            }
        } catch (Throwable) {
            // getColumnMeta() isn't implemented by every PDO driver (e.g. pgsql) —
            // an empty column list just means mapping stays empty until a row exists.
        }

        return $columns;
    }

    /**
     * Opens a uniquely-named dynamic connection from the stored config
     * and returns its name (for DB::purge()) plus its raw PDO instance.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: string, 1: PDO}
     */
    private function connect(array $config): array
    {
        $name = 'importer_dynamic_'.Str::random(12);

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

        return [$name, DB::connection($name)->getPdo()];
    }

    /**
     * Maps the driver value chosen in the wizard to the driver name
     * Laravel's connection factory understands. Falls through unknown
     * values unchanged so tests can point this channel at sqlite.
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
