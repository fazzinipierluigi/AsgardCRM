<?php

namespace App\Enums;

enum ImporterRunStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case PartialFailure = 'partial_failure';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'In esecuzione',
            self::Success => 'Completato',
            self::PartialFailure => 'Completato con errori',
            self::Failed => 'Fallito',
        };
    }
}
