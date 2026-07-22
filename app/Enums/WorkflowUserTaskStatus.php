<?php

namespace App\Enums;

enum WorkflowUserTaskStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In attesa',
            self::Completed => 'Completato',
        };
    }
}
