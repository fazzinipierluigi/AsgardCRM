<?php

namespace Fazzinipierluigi\CrmCore\Enums;

enum MailEncryption: string
{
    case Ssl = 'ssl';
    case Tls = 'tls';
    case StartTls = 'starttls';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Ssl => 'SSL',
            self::Tls => 'TLS',
            self::StartTls => 'STARTTLS',
            self::None => 'Nessuna',
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
