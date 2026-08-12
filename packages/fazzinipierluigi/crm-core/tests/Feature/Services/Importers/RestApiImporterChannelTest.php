<?php

use Fazzinipierluigi\CrmCore\Models\Importer;
use Fazzinipierluigi\CrmCore\Services\Importers\Channels\RestApiImporterChannel;
use Illuminate\Support\Facades\Http;

test('fetch extracts rows from a data key', function () {
    Http::fake([
        'api.example.com/*' => Http::response(['data' => [
            ['nome' => 'Mario Rossi'],
            ['nome' => 'Luigi Verdi'],
        ]]),
    ]);

    $importer = new Importer(['channel' => 'rest_api', 'config' => [
        'method' => 'GET', 'endpoint' => 'https://api.example.com/contacts', 'auth_type' => 'none',
    ]]);

    $rows = iterator_to_array((new RestApiImporterChannel)->fetch($importer));

    expect($rows)->toBe([['nome' => 'Mario Rossi'], ['nome' => 'Luigi Verdi']]);
});

test('fetch accepts a bare json list response', function () {
    Http::fake(['api.example.com/*' => Http::response([['nome' => 'Mario Rossi']])]);

    $importer = new Importer(['channel' => 'rest_api', 'config' => [
        'method' => 'GET', 'endpoint' => 'https://api.example.com/contacts', 'auth_type' => 'none',
    ]]);

    $rows = iterator_to_array((new RestApiImporterChannel)->fetch($importer));

    expect($rows)->toBe([['nome' => 'Mario Rossi']]);
});

test('fetch wraps a single json object as one row', function () {
    Http::fake(['api.example.com/*' => Http::response(['nome' => 'Mario Rossi'])]);

    $importer = new Importer(['channel' => 'rest_api', 'config' => [
        'method' => 'GET', 'endpoint' => 'https://api.example.com/contacts', 'auth_type' => 'none',
    ]]);

    $rows = iterator_to_array((new RestApiImporterChannel)->fetch($importer));

    expect($rows)->toBe([['nome' => 'Mario Rossi']]);
});

test('bearer token authentication sends the authorization header', function () {
    Http::fake(['api.example.com/*' => Http::response([])]);

    $importer = new Importer(['channel' => 'rest_api', 'config' => [
        'method' => 'GET', 'endpoint' => 'https://api.example.com/contacts', 'auth_type' => 'bearer', 'auth_token' => 'my-token',
    ]]);

    iterator_to_array((new RestApiImporterChannel)->fetch($importer));

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer my-token'));
});

test('preview reports ok false when the request fails', function () {
    Http::fake(['api.example.com/*' => Http::response('Server error', 500)]);

    $importer = new Importer(['channel' => 'rest_api', 'config' => [
        'method' => 'GET', 'endpoint' => 'https://api.example.com/contacts', 'auth_type' => 'none',
    ]]);

    $result = (new RestApiImporterChannel)->preview($importer);

    expect($result['ok'])->toBeFalse();
});
