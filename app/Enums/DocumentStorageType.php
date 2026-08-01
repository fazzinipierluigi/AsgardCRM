<?php

namespace App\Enums;

enum DocumentStorageType: string
{
    case Local = 'local';
    case S3 = 's3';
    case Ftp = 'ftp';
    case Sftp = 'sftp';

    public function label(): string
    {
        return match ($this) {
            self::Local => 'Locale (disco del server)',
            self::S3 => 'Bucket S3-compatibile',
            self::Ftp => 'Server FTP',
            self::Sftp => 'Server SFTP',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
