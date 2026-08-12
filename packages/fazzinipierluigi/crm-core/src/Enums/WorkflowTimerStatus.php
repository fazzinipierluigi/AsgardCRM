<?php

namespace Fazzinipierluigi\CrmCore\Enums;

enum WorkflowTimerStatus: string
{
    case Pending = 'pending';
    case Fired = 'fired';
    // Only ever set on a Boundary Timer's own WorkflowTimer row, when
    // its host node completes through its normal path before the timer
    // was due — see WorkflowTokenTransitioner::traverse().
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In attesa',
            self::Fired => 'Eseguito',
            self::Cancelled => 'Annullato',
        };
    }
}
