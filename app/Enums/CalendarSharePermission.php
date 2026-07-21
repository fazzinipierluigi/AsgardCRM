<?php

namespace App\Enums;

enum CalendarSharePermission: string
{
    case View = 'view';
    case Edit = 'edit';

    public function label(): string
    {
        return match ($this) {
            self::View => 'Visualizza',
            self::Edit => 'Modifica',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $permission) => [$permission->value => $permission->label()])->all();
    }
}
