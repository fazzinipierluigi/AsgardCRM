<?php

namespace Fazzinipierluigi\AsgardCRM\Models;

use Fazzinipierluigi\AsgardCRM\Enums\DocumentStorageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row (always id 1) holding which disk the "Documenti" entity
 * (Fazzinipierluigi\AsgardCRM\Http\Controllers\DocumentController) stores files on — local disk,
 * an S3-compatible bucket, or an FTP/SFTP server — plus its type-specific
 * credentials, encrypted at rest. See Fazzinipierluigi\AsgardCRM\Services\DocumentStorageDiskResolver
 * for how `config` is turned into an actual Flysystem disk.
 */
#[Fillable(['type', 'config'])]
class DocumentStorageSetting extends Model
{
    protected function casts(): array
    {
        return [
            'type' => DocumentStorageType::class,
            'config' => 'encrypted:array',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::create(['type' => DocumentStorageType::Local]);
    }
}
