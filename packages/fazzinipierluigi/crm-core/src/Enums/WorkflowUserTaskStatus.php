<?php

namespace Fazzinipierluigi\CrmCore\Enums;

enum WorkflowUserTaskStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    // Set when a Boundary Timer attached to this task's node fires
    // before the user completed it — the task is no longer actionable,
    // the flow already moved on down the timer's own branch.
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In attesa',
            self::Completed => 'Completato',
            self::Expired => 'Scaduto',
        };
    }
}
