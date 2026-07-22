<?php

namespace App\Enums;

enum WorkflowActionPhase: string
{
    case Before = 'before';
    case After = 'after';

    public function label(): string
    {
        return match ($this) {
            self::Before => 'In ingresso',
            self::After => 'In uscita',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $phase) => [$phase->value => $phase->label()])->all();
    }
}
