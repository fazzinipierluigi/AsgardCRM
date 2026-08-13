<?php

namespace Fazzinipierluigi\AsgardCRM\Enums;

enum WorkflowNodeExecutionStatus: string
{
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'In attesa',
            self::Completed => 'Completato',
            self::Failed => 'Fallito',
        };
    }
}
