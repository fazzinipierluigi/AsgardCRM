<?php

use App\Services\DynamicDatabaseConnector;
use Illuminate\Support\Facades\DB;

function dynamicConnectorSqliteDatabase(): string
{
    $path = tempnam(sys_get_temp_dir(), 'workflow_sql_').'.sqlite';
    touch($path);

    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec('CREATE TABLE ordini (id INTEGER PRIMARY KEY, cliente_id INTEGER, totale REAL)');
    $pdo->exec('INSERT INTO ordini (cliente_id, totale) VALUES (1, 100.5)');
    $pdo->exec('INSERT INTO ordini (cliente_id, totale) VALUES (2, 50)');

    return $path;
}

test('run executes a query on the dynamic connection and returns its rows', function () {
    $path = dynamicConnectorSqliteDatabase();

    $rows = (new DynamicDatabaseConnector)->run(
        ['driver' => 'sqlite', 'database' => $path],
        'test_dynamic',
        fn ($connection) => $connection->select('SELECT * FROM ordini WHERE cliente_id = ?', [1]),
    );

    expect($rows)->toHaveCount(1);
    expect($rows[0]->totale)->toBe(100.5);

    unlink($path);
});

test('run always purges the dynamic connection afterwards, even on failure', function () {
    $path = dynamicConnectorSqliteDatabase();

    try {
        (new DynamicDatabaseConnector)->run(
            ['driver' => 'sqlite', 'database' => $path],
            'test_dynamic',
            fn ($connection) => $connection->select('SELECT * FROM tabella_inesistente'),
        );
    } catch (Throwable) {
        // expected
    }

    $dynamicConnections = array_filter(array_keys(DB::getConnections()), fn ($name) => str_starts_with($name, 'test_dynamic_'));
    expect($dynamicConnections)->toBe([]);

    unlink($path);
});
