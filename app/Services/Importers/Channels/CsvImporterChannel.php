<?php

namespace App\Services\Importers\Channels;

use App\Models\Importer;
use App\Services\Importers\ImporterChannelInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Imports from a CSV file, either a local absolute path or a URL.
 * Streams row by row via fgetcsv() rather than loading the whole
 * resource into memory.
 */
class CsvImporterChannel implements ImporterChannelInterface
{
    public function preview(Importer $importer): array
    {
        try {
            foreach ($this->fetch($importer) as $row) {
                return ['ok' => true, 'columns' => array_keys($row), 'sample' => $row];
            }

            return ['ok' => true, 'columns' => [], 'sample' => []];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetch(Importer $importer): iterable
    {
        $config = $importer->config ?? [];
        $delimiter = (string) ($config['delimiter'] ?? ',') ?: ',';
        $hasHeader = (bool) ($config['has_header'] ?? true);

        $handle = $this->open((string) ($config['path_or_url'] ?? ''));

        try {
            $header = null;

            while (($fields = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($fields === [null]) {
                    continue;
                }

                if ($hasHeader && $header === null) {
                    $header = $fields;

                    continue;
                }

                $keys = $header ?? array_map(fn (int $i) => "Colonna {$i}", range(1, count($fields)));
                $count = min(count($keys), count($fields));

                yield array_combine(array_slice($keys, 0, $count), array_slice($fields, 0, $count));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return resource
     */
    private function open(string $pathOrUrl)
    {
        if (preg_match('#^https?://#i', $pathOrUrl) === 1) {
            $content = Http::get($pathOrUrl)->throw()->body();
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $content);
            rewind($stream);

            return $stream;
        }

        if (! is_readable($pathOrUrl)) {
            throw new RuntimeException("File non leggibile: {$pathOrUrl}");
        }

        $handle = fopen($pathOrUrl, 'r');

        if ($handle === false) {
            throw new RuntimeException("Impossibile aprire il file: {$pathOrUrl}");
        }

        return $handle;
    }
}
