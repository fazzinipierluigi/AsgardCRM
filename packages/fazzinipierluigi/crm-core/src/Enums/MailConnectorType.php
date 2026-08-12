<?php

namespace Fazzinipierluigi\CrmCore\Enums;

/**
 * Deliberately separate from the calendar's ConnectorType, even though
 * the two case sets look identical today — this lets the mail and
 * calendar connector systems diverge (different Graph app permission
 * scopes, different admin UI copy) without coupling them.
 */
enum MailConnectorType: string
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
