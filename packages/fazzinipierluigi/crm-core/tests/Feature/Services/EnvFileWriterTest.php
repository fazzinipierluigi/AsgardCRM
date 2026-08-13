<?php

use Fazzinipierluigi\AsgardCRM\Services\EnvFileWriter;

function tempEnvFile(string $contents): string
{
    $path = sys_get_temp_dir().'/'.uniqid('env_', true).'.env';
    file_put_contents($path, $contents);

    return $path;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/env_*.env') as $file) {
        @unlink($file);
    }
});

test('replaces the value of an existing key in place', function () {
    $path = tempEnvFile("APP_NAME=Laravel\nDB_CONNECTION=sqlite\n");

    (new EnvFileWriter($path))->set(['DB_CONNECTION' => 'pgsql']);

    expect(file_get_contents($path))
        ->toContain("APP_NAME=Laravel\n")
        ->toContain('DB_CONNECTION=pgsql')
        ->not->toContain('DB_CONNECTION=sqlite');
});

test('appends a key that does not already exist', function () {
    $path = tempEnvFile("APP_NAME=Laravel\n");

    (new EnvFileWriter($path))->set(['DB_HOST' => '127.0.0.1']);

    expect(file_get_contents($path))
        ->toContain("APP_NAME=Laravel\n")
        ->toContain('DB_HOST=127.0.0.1');
});

test('leaves unrelated lines untouched', function () {
    $path = tempEnvFile("APP_NAME=Laravel\nAPP_ENV=local\nDB_CONNECTION=sqlite\n");

    (new EnvFileWriter($path))->set(['DB_CONNECTION' => 'mysql']);

    expect(file_get_contents($path))
        ->toContain("APP_ENV=local\n");
});

test('quotes values containing spaces', function () {
    $path = tempEnvFile("APP_NAME=Laravel\n");

    (new EnvFileWriter($path))->set(['DB_PASSWORD' => 'a secret pass']);

    expect(file_get_contents($path))->toContain('DB_PASSWORD="a secret pass"');
});

test('writes multiple keys in a single call', function () {
    $path = tempEnvFile("APP_NAME=Laravel\n");

    (new EnvFileWriter($path))->set([
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => '127.0.0.1',
        'DB_DATABASE' => 'asgardcrm',
    ]);

    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('DB_CONNECTION=mysql')
        ->toContain('DB_HOST=127.0.0.1')
        ->toContain('DB_DATABASE=asgardcrm');
});
