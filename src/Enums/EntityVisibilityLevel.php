<?php

namespace Fazzinipierluigi\AsgardCRM\Enums;

enum EntityVisibilityLevel: string
{
    case OwnOnly = 'own_only';
    case OwnManageOthersRead = 'own_manage_others_read';
    case OwnManageOthersEdit = 'own_manage_others_edit';
    case Full = 'full';

    public function label(): string
    {
        return match ($this) {
            self::OwnOnly => 'Solo le proprie',
            self::OwnManageOthersRead => 'Le proprie, in sola lettura quelle altrui',
            self::OwnManageOthersEdit => 'Le proprie, in lettura e modifica quelle altrui',
            self::Full => 'Controllo completo su tutte',
        };
    }

    /**
     * Permissiveness ranking used to pick the effective level for a user
     * with multiple roles (the highest-ranked one wins).
     */
    public function rank(): int
    {
        return match ($this) {
            self::OwnOnly => 0,
            self::OwnManageOthersRead => 1,
            self::OwnManageOthersEdit => 2,
            self::Full => 3,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $level) => [$level->value => $level->label()])->all();
    }
}
