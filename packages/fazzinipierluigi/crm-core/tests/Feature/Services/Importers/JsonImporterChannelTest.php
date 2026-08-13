<?php

use Fazzinipierluigi\AsgardCRM\Models\Importer;
use Fazzinipierluigi\AsgardCRM\Services\Importers\Channels\JsonImporterChannel;

test('fetch decodes a top-level json array of objects', function () {
    $path = tempnam(sys_get_temp_dir(), 'importer_json_');
    file_put_contents($path, json_encode([
        ['nome' => 'Mario Rossi', 'email' => 'mario@example.com'],
        ['nome' => 'Luigi Verdi', 'email' => 'luigi@example.com'],
    ]));

    $importer = new Importer(['channel' => 'json', 'config' => ['path_or_url' => $path]]);

    $rows = iterator_to_array((new JsonImporterChannel)->fetch($importer));

    expect($rows)->toBe([
        ['nome' => 'Mario Rossi', 'email' => 'mario@example.com'],
        ['nome' => 'Luigi Verdi', 'email' => 'luigi@example.com'],
    ]);

    unlink($path);
});

test('fetch rejects a non-array-of-objects json document', function () {
    $path = tempnam(sys_get_temp_dir(), 'importer_json_');
    file_put_contents($path, json_encode(['nome' => 'Mario Rossi']));

    $importer = new Importer(['channel' => 'json', 'config' => ['path_or_url' => $path]]);

    $result = (new JsonImporterChannel)->preview($importer);

    expect($result['ok'])->toBeFalse();

    unlink($path);
});

test('preview reports ok false for an unreadable path', function () {
    $importer = new Importer(['channel' => 'json', 'config' => ['path_or_url' => '/no/such/file.json']]);

    $result = (new JsonImporterChannel)->preview($importer);

    expect($result['ok'])->toBeFalse();
});
