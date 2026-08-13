<?php

use Fazzinipierluigi\AsgardCRM\Models\Importer;
use Fazzinipierluigi\AsgardCRM\Services\Importers\Channels\CsvImporterChannel;

test('fetch streams every row keyed by header', function () {
    $path = tempnam(sys_get_temp_dir(), 'importer_csv_');
    file_put_contents($path, "nome;email\nMario Rossi;mario@example.com\nLuigi Verdi;luigi@example.com\n");

    $importer = new Importer(['channel' => 'csv', 'config' => ['path_or_url' => $path, 'delimiter' => ';', 'has_header' => true]]);

    $rows = iterator_to_array((new CsvImporterChannel)->fetch($importer));

    expect($rows)->toBe([
        ['nome' => 'Mario Rossi', 'email' => 'mario@example.com'],
        ['nome' => 'Luigi Verdi', 'email' => 'luigi@example.com'],
    ]);

    unlink($path);
});

test('fetch synthesizes column names when there is no header row', function () {
    $path = tempnam(sys_get_temp_dir(), 'importer_csv_');
    file_put_contents($path, "Mario Rossi,mario@example.com\n");

    $importer = new Importer(['channel' => 'csv', 'config' => ['path_or_url' => $path, 'delimiter' => ',', 'has_header' => false]]);

    $rows = iterator_to_array((new CsvImporterChannel)->fetch($importer));

    expect($rows)->toBe([
        ['Colonna 1' => 'Mario Rossi', 'Colonna 2' => 'mario@example.com'],
    ]);

    unlink($path);
});

test('preview returns ok false for a missing file', function () {
    $importer = new Importer(['channel' => 'csv', 'config' => ['path_or_url' => '/no/such/file.csv']]);

    $result = (new CsvImporterChannel)->preview($importer);

    expect($result['ok'])->toBeFalse();
    expect($result['message'])->not->toBeEmpty();
});
