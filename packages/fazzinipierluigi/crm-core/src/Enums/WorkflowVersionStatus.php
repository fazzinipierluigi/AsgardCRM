<?php

namespace Fazzinipierluigi\CrmCore\Enums;

enum WorkflowVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Published => 'Pubblicata',
        };
    }
}
