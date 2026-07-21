<?php

namespace App\Enums;

enum ImporterScheduleType: string
{
    case Manual = 'manual';
    case Cron = 'cron';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manuale',
            self::Cron => 'Pianificata (cron)',
            self::Both => 'Manuale e pianificata',
        };
    }

    /**
     * Whether a "run now" action should be available for this schedule type.
     */
    public function runsManually(): bool
    {
        return $this === self::Manual || $this === self::Both;
    }

    /**
     * Whether the importer should be considered by the cron dispatcher.
     */
    public function runsOnCron(): bool
    {
        return $this === self::Cron || $this === self::Both;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
