<?php

namespace App\Enums;

enum WorkflowTimerStatus: string
{
    case Pending = 'pending';
    case Fired = 'fired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In attesa',
            self::Fired => 'Eseguito',
        };
    }
}
