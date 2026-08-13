<?php

namespace Fazzinipierluigi\AsgardCRM\Enums;

enum WorkflowTokenStatus: string
{
    case Active = 'active';
    case WaitingTimer = 'waiting_timer';
    case WaitingUserTask = 'waiting_user_task';
    case WaitingJoin = 'waiting_join';
    case WaitingSubworkflow = 'waiting_subworkflow';
    case WaitingActivity = 'waiting_activity';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Attivo',
            self::WaitingTimer => 'In attesa del timer',
            self::WaitingUserTask => 'In attesa dell\'utente',
            self::WaitingJoin => 'In attesa delle altre strade',
            self::WaitingSubworkflow => 'In attesa del subworkflow',
            self::WaitingActivity => 'In attesa dell\'attività in coda',
            self::Completed => 'Completato',
            self::Cancelled => 'Annullato',
        };
    }

    public function isWaiting(): bool
    {
        return in_array($this, [
            self::WaitingTimer,
            self::WaitingUserTask,
            self::WaitingJoin,
            self::WaitingSubworkflow,
            self::WaitingActivity,
        ], true);
    }
}
