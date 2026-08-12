<?php

namespace Fazzinipierluigi\CrmCore\Services\Importers\Channels;

use Fazzinipierluigi\CrmCore\Models\Importer;
use Fazzinipierluigi\CrmCore\Services\Importers\ImporterChannelInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Imports from a JSON file/endpoint, either a local absolute path or a
 * URL. Expects a top-level JSON array of objects — no nested "data"
 * path (v1 scope, unlike the REST API channel which already reads a
 * live API response shape).
 */
class JsonImporterChannel implements ImporterChannelInterface
{
    public function preview(Importer $importer): array
    {
        try {
            $rows = $this->rows($importer);
            $sample = $rows[0] ?? null;

            return [
                'ok' => true,
                'columns' => $sample !== null ? array_keys($sample) : [],
                'sample' => $sample ?? [],
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetch(Importer $importer): iterable
    {
        foreach ($this->rows($importer) as $row) {
            yield $row;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(Importer $importer): array
    {
        $config = $importer->config ?? [];
        $content = $this->content((string) ($config['path_or_url'] ?? ''));
        $data = json_decode($content, true);

        if (! is_array($data) || ! array_is_list($data)) {
            throw new RuntimeException('Il JSON deve contenere un array di oggetti.');
        }

        return array_map(fn ($row) => is_array($row) ? $row : ['value' => $row], $data);
    }

    private function content(string $pathOrUrl): string
    {
        if (preg_match('#^https?://#i', $pathOrUrl) === 1) {
            return Http::get($pathOrUrl)->throw()->body();
        }

        if (! is_readable($pathOrUrl)) {
            throw new RuntimeException("File non leggibile: {$pathOrUrl}");
        }

        return file_get_contents($pathOrUrl);
    }
}
