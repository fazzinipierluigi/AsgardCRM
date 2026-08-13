<?php

namespace Fazzinipierluigi\AsgardCRM\Enums;

enum WorkflowTimerUnit: string
{
    case Minutes = 'minutes';
    case Hours = 'hours';
    case Days = 'days';

    public function label(): string
    {
        return match ($this) {
            self::Minutes => 'Minuti',
            self::Hours => 'Ore',
            self::Days => 'Giorni',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $unit) => [$unit->value => $unit->label()])->all();
    }
}
