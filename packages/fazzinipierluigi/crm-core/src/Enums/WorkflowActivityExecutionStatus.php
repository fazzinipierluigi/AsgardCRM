<?php

namespace Fazzinipierluigi\CrmCore\Enums;

enum WorkflowActivityExecutionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In corso',
            self::Completed => 'Completata',
        };
    }
}
