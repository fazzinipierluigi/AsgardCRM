<?php

namespace Fazzinipierluigi\AsgardCRM\Enums;

enum ConnectorType: string
{
    case ExchangeGraph = 'exchange_graph';
    case ExchangeEws = 'exchange_ews';

    public function label(): string
    {
        return match ($this) {
            self::ExchangeGraph => 'Exchange (Microsoft Graph)',
            self::ExchangeEws => 'Exchange (EWS on-premise)',
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
