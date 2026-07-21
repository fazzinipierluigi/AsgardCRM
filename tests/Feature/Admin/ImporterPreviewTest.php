<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('preview returns columns and a sample row for a csv source', function () {
    $path = tempnam(sys_get_temp_dir(), 'importer_csv_');
    file_put_contents($path, "nome,email\nMario Rossi,mario@example.com\nLuigi Verdi,luigi@example.com\n");

    $response = $this->actingAs(adminUser())->postJson(route('admin.importers.preview'), [
        'channel' => 'csv',
        'path_or_url' => $path,
        'delimiter' => ',',
        'has_header' => '1',
    ]);

    $response->assertOk();
    $response->assertJson([
        'ok' => true,
        'columns' => ['nome', 'email'],
        'sample' => ['nome' => 'Mario Rossi', 'email' => 'mario@example.com'],
    ]);

    unlink($path);
});

test('preview returns columns and a sample row for a json source', function () {
    $path = tempnam(sys_get_temp_dir(), 'importer_json_');
    file_put_contents($path, json_encode([
        ['nome' => 'Mario Rossi', 'email' => 'mario@example.com'],
        ['nome' => 'Luigi Verdi', 'email' => 'luigi@example.com'],
    ]));

    $response = $this->actingAs(adminUser())->postJson(route('admin.importers.preview'), [
        'channel' => 'json',
        'path_or_url' => $path,
    ]);

    $response->assertOk();
    $response->assertJson([
        'ok' => true,
        'columns' => ['nome', 'email'],
        'sample' => ['nome' => 'Mario Rossi', 'email' => 'mario@example.com'],
    ]);

    unlink($path);
});

test('preview calls a rest api endpoint and extracts rows', function () {
    Http::fake([
        'api.example.com/*' => Http::response([
            'data' => [
                ['nome' => 'Mario Rossi', 'email' => 'mario@example.com'],
            ],
        ]),
    ]);

    $response = $this->actingAs(adminUser())->postJson(route('admin.importers.preview'), [
        'channel' => 'rest_api',
        'method' => 'GET',
        'endpoint' => 'https://api.example.com/contacts',
        'auth_type' => 'none',
    ]);

    $response->assertOk();
    $response->assertJson([
        'ok' => true,
        'columns' => ['nome', 'email'],
        'sample' => ['nome' => 'Mario Rossi', 'email' => 'mario@example.com'],
    ]);
});

test('preview reports ok false on an unreadable csv path', function () {
    $response = $this->actingAs(adminUser())->postJson(route('admin.importers.preview'), [
        'channel' => 'csv',
        'path_or_url' => '/this/path/does/not/exist.csv',
        'delimiter' => ',',
        'has_header' => '1',
    ]);

    $response->assertOk();
    $response->assertJson(['ok' => false]);
    expect($response->json('message'))->not->toBeEmpty();
});

test('preview validates required fields per channel', function () {
    $response = $this->actingAs(adminUser())->postJson(route('admin.importers.preview'), [
        'channel' => 'database',
    ]);

    $response->assertJsonValidationErrors(['driver', 'host', 'database', 'username', 'query']);
});
