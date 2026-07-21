<?php

namespace App\Enums;

enum ConnectorSyncDirection: string
{
    case Bidirectional = 'bidirectional';
    case ImportOnly = 'import_only';
    case ExportOnly = 'export_only';

    public function label(): string
    {
        return match ($this) {
            self::Bidirectional => 'Bidirezionale',
            self::ImportOnly => 'Solo importazione',
            self::ExportOnly => 'Solo esportazione',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $direction) => [$direction->value => $direction->label()])->all();
    }
}
