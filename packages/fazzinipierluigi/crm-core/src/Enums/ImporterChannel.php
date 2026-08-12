<?php

namespace Fazzinipierluigi\CrmCore\Enums;

enum ImporterChannel: string
{
    case Database = 'database';
    case RestApi = 'rest_api';
    case Csv = 'csv';
    case Json = 'json';

    public function label(): string
    {
        return match ($this) {
            self::Database => 'Database',
            self::RestApi => 'API REST',
            self::Csv => 'CSV',
            self::Json => 'JSON',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $channel) => [$channel->value => $channel->label()])->all();
    }
}
