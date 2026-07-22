<?php

namespace App\Enums;

enum WorkflowInstanceStatus: string
{
    case Running = 'running';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'In esecuzione',
            self::Waiting => 'In attesa',
            self::Completed => 'Completato',
            self::Failed => 'Fallito',
            self::Cancelled => 'Annullato',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Cancelled], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
