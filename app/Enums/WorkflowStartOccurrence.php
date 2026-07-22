<?php

namespace App\Enums;

/**
 * For an entity-bound start trigger (created/updated/both): whether
 * the workflow can start again for a record that already started it
 * once before.
 */
enum WorkflowStartOccurrence: string
{
    case Once = 'once';
    case EveryTime = 'every_time';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'Avvia una sola volta',
            self::EveryTime => 'Avvia ogni volta',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $occurrence) => [$occurrence->value => $occurrence->label()])->all();
    }
}
