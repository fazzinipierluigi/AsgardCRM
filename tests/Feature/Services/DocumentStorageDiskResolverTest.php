<?php

use Fazzinipierluigi\CrmCore\Enums\DocumentStorageType;
use Fazzinipierluigi\CrmCore\Models\DocumentStorageSetting;
use Fazzinipierluigi\CrmCore\Services\DocumentStorageDiskResolver;

test('builds an s3 disk config from the stored settings', function () {
    $setting = new DocumentStorageSetting([
        'type' => DocumentStorageType::S3,
        'config' => ['key' => 'AKIA123', 'secret' => 'shh', 'region' => 'eu-west-1', 'bucket' => 'documenti-crm', 'endpoint' => 'https://s3.example.com', 'use_path_style_endpoint' => true],
    ]);

    $config = (new DocumentStorageDiskResolver)->diskConfig($setting);

    expect($config)->toBe([
        'driver' => 's3',
        'key' => 'AKIA123',
        'secret' => 'shh',
        'region' => 'eu-west-1',
        'bucket' => 'documenti-crm',
        'endpoint' => 'https://s3.example.com',
        'use_path_style_endpoint' => true,
    ]);
});

test('builds an sftp disk config from the stored settings', function () {
    $setting = new DocumentStorageSetting([
        'type' => DocumentStorageType::Sftp,
        'config' => ['host' => 'sftp.example.com', 'username' => 'crm', 'password' => 'secret', 'port' => '2222', 'root' => '/documenti'],
    ]);

    $config = (new DocumentStorageDiskResolver)->diskConfig($setting);

    expect($config)->toBe([
        'driver' => 'sftp',
        'host' => 'sftp.example.com',
        'username' => 'crm',
        'password' => 'secret',
        'port' => 2222,
        'root' => '/documenti',
    ]);
});

test('builds an ftp disk config with sensible defaults for missing fields', function () {
    $setting = new DocumentStorageSetting(['type' => DocumentStorageType::Ftp, 'config' => []]);

    $config = (new DocumentStorageDiskResolver)->diskConfig($setting);

    expect($config)->toBe([
        'driver' => 'ftp',
        'host' => null,
        'username' => null,
        'password' => null,
        'port' => 21,
        'root' => '',
        'ssl' => false,
        'passive' => true,
    ]);
});
