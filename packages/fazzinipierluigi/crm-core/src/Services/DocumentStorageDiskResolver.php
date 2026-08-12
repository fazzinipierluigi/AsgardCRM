<?php

namespace Fazzinipierluigi\CrmCore\Services;

use Fazzinipierluigi\CrmCore\Enums\DocumentStorageType;
use Fazzinipierluigi\CrmCore\Models\DocumentStorageSetting;

/**
 * Resolves the disk name Fazzinipierluigi\CrmCore\Http\Controllers\DocumentController should
 * read/write documents on, driven by the admin-configurable
 * DocumentStorageSetting singleton. The "local" type keeps using the
 * app's already-registered 'local' disk untouched (so Storage::fake('local')
 * in tests keeps working as-is); S3/FTP/SFTP register an ephemeral
 * 'documents' disk at runtime instead of a static config/filesystems.php
 * entry, since the connection details live in the encrypted DB config.
 */
class DocumentStorageDiskResolver
{
    private const DYNAMIC_DISK = 'documents';

    public function diskName(): string
    {
        $setting = DocumentStorageSetting::current();

        if ($setting->type === DocumentStorageType::Local) {
            return 'local';
        }

        config(['filesystems.disks.'.self::DYNAMIC_DISK => $this->diskConfig($setting)]);

        return self::DYNAMIC_DISK;
    }

    /**
     * @return array<string, mixed>
     */
    public function diskConfig(DocumentStorageSetting $setting): array
    {
        $config = $setting->config ?? [];

        return match ($setting->type) {
            DocumentStorageType::S3 => [
                'driver' => 's3',
                'key' => $config['key'] ?? null,
                'secret' => $config['secret'] ?? null,
                'region' => $config['region'] ?? null,
                'bucket' => $config['bucket'] ?? null,
                'endpoint' => $config['endpoint'] ?? null,
                'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? false),
            ],
            DocumentStorageType::Ftp => [
                'driver' => 'ftp',
                'host' => $config['host'] ?? null,
                'username' => $config['username'] ?? null,
                'password' => $config['password'] ?? null,
                'port' => (int) ($config['port'] ?? 21),
                'root' => $config['root'] ?? '',
                'ssl' => (bool) ($config['ssl'] ?? false),
                'passive' => true,
            ],
            DocumentStorageType::Sftp => [
                'driver' => 'sftp',
                'host' => $config['host'] ?? null,
                'username' => $config['username'] ?? null,
                'password' => $config['password'] ?? null,
                'port' => (int) ($config['port'] ?? 22),
                'root' => $config['root'] ?? '',
            ],
            DocumentStorageType::Local => [
                'driver' => 'local',
                'root' => storage_path('app/private'),
            ],
        };
    }
}
