<?php

namespace Fazzinipierluigi\CrmCore\Services\Importers\Channels;

use Fazzinipierluigi\CrmCore\Models\Importer;
use Fazzinipierluigi\CrmCore\Services\Importers\ImporterChannelInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Imports from a REST API endpoint. A single call, no pagination
 * (v1 scope) — the response is expected to be a JSON list, an object
 * carrying the list under a "data" key, or a single object (treated
 * as one row).
 */
class RestApiImporterChannel implements ImporterChannelInterface
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
        $data = $this->request($importer->config ?? [])->json();

        return $this->extractRows($data);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function request(array $config): Response
    {
        $request = $this->withAuth(Http::acceptJson(), $config);

        $method = strtoupper((string) ($config['method'] ?? 'GET'));
        $endpoint = (string) ($config['endpoint'] ?? '');
        $params = json_decode((string) ($config['params_json'] ?? '') ?: '[]', true);
        $params = is_array($params) ? $params : [];

        $response = in_array($method, ['GET', 'DELETE'], true)
            ? $request->send($method, $endpoint, ['query' => $params])
            : $request->send($method, $endpoint, ['json' => $params]);

        return $response->throw();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function withAuth(PendingRequest $request, array $config): PendingRequest
    {
        return match ($config['auth_type'] ?? 'none') {
            'basic' => $request->withBasicAuth((string) ($config['auth_username'] ?? ''), (string) ($config['auth_password'] ?? '')),
            'bearer' => $request->withToken((string) ($config['auth_token'] ?? '')),
            'api_key' => $request->withHeaders([(string) ($config['auth_api_key_name'] ?? 'X-Api-Key') => (string) ($config['auth_api_key_value'] ?? '')]),
            default => $request,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractRows(mixed $data): array
    {
        if (is_array($data) && array_is_list($data)) {
            return $data;
        }

        if (is_array($data) && is_array($data['data'] ?? null) && array_is_list($data['data'])) {
            return $data['data'];
        }

        if (is_array($data)) {
            return [$data];
        }

        return [];
    }
}
