<?php

namespace App\Services\Importers\Channels;

use App\Models\Importer;
use App\Services\DynamicDatabaseConnector;
use App\Services\Importers\ImporterChannelInterface;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Imports from an arbitrary external database via a raw query the admin
 * configured (no SELECT-only restriction — the query runs exactly as
 * entered, on a dynamic read connection scoped to this run).
 */
class DatabaseImporterChannel implements ImporterChannelInterface
{
    public function __construct(private readonly DynamicDatabaseConnector $connector = new DynamicDatabaseConnector) {}

    public function preview(Importer $importer): array
    {
        $config = $importer->config ?? [];

        try {
            [$name, $connection] = $this->connector->connect($config, 'importer_dynamic');

            try {
                $stmt = $connection->getPdo()->query($config['query'] ?? '');

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
        [$name, $connection] = $this->connector->connect($config, 'importer_dynamic');

        try {
            $stmt = $connection->getPdo()->query($config['query'] ?? '');

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
}
