<?php

use App\Models\Importer;
use App\Services\Importers\Channels\DatabaseImporterChannel;
use Illuminate\Support\Facades\DB;

/**
 * Builds a standalone sqlite file (distinct from the app's own :memory:
 * test connection) to exercise DatabaseImporterChannel against a real
 * external-looking database via the 'sqlite' driver passthrough.
 */
function externalSqliteDatabase(): string
{
    $path = tempnam(sys_get_temp_dir(), 'importer_db_').'.sqlite';
    touch($path);

    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec('CREATE TABLE people (id INTEGER PRIMARY KEY, nome TEXT, email TEXT)');
    $pdo->exec("INSERT INTO people (nome, email) VALUES ('Mario Rossi', 'mario@example.com')");
    $pdo->exec("INSERT INTO people (nome, email) VALUES ('Luigi Verdi', 'luigi@example.com')");

    return $path;
}

test('fetch runs the configured query against the external connection', function () {
    $path = externalSqliteDatabase();

    $importer = new Importer(['channel' => 'database', 'config' => [
        'driver' => 'sqlite', 'database' => $path, 'query' => 'SELECT * FROM people ORDER BY id',
    ]]);

    $rows = iterator_to_array((new DatabaseImporterChannel)->fetch($importer));

    expect($rows)->toHaveCount(2);
    expect($rows[0]['nome'])->toBe('Mario Rossi');
    expect($rows[1]['email'])->toBe('luigi@example.com');

    unlink($path);
});

test('fetch does not leak dynamic connections after running', function () {
    $path = externalSqliteDatabase();

    $importer = new Importer(['channel' => 'database', 'config' => [
        'driver' => 'sqlite', 'database' => $path, 'query' => 'SELECT * FROM people',
    ]]);

    iterator_to_array((new DatabaseImporterChannel)->fetch($importer));

    $dynamicConnections = array_filter(array_keys(DB::getConnections()), fn ($name) => str_starts_with($name, 'importer_dynamic_'));

    expect($dynamicConnections)->toBe([]);

    unlink($path);
});

test('preview returns columns and the first row', function () {
    $path = externalSqliteDatabase();

    $importer = new Importer(['channel' => 'database', 'config' => [
        'driver' => 'sqlite', 'database' => $path, 'query' => 'SELECT nome, email FROM people ORDER BY id',
    ]]);

    $result = (new DatabaseImporterChannel)->preview($importer);

    expect($result['ok'])->toBeTrue();
    expect($result['columns'])->toBe(['nome', 'email']);
    expect($result['sample'])->toBe(['nome' => 'Mario Rossi', 'email' => 'mario@example.com']);

    unlink($path);
});

test('an invalid query is reported as ok false rather than throwing', function () {
    $path = externalSqliteDatabase();

    $importer = new Importer(['channel' => 'database', 'config' => [
        'driver' => 'sqlite', 'database' => $path, 'query' => 'SELECT * FROM tabella_inesistente',
    ]]);

    $result = (new DatabaseImporterChannel)->preview($importer);

    expect($result['ok'])->toBeFalse();

    unlink($path);
});
