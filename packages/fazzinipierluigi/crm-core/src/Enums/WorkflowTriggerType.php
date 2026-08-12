<?php

namespace Fazzinipierluigi\CrmCore\Enums;

enum WorkflowTriggerType: string
{
    case Manual = 'manual';
    case Cron = 'cron';
    case EntityCreated = 'entity_created';
    case EntityUpdated = 'entity_updated';
    case EntityCreatedOrUpdated = 'entity_created_or_updated';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Avvio manuale',
            self::Cron => 'Avvio via timer/cron',
            self::EntityCreated => 'Alla creazione di un\'entità',
            self::EntityUpdated => 'Alla modifica di un\'entità',
            self::EntityCreatedOrUpdated => 'Alla creazione o modifica di un\'entità',
        };
    }

    /**
     * Whether this trigger binds the instance to the entity record that
     * fired it (a mandatory system variable holding that record).
     */
    public function isEntityBound(): bool
    {
        return in_array($this, [self::EntityCreated, self::EntityUpdated, self::EntityCreatedOrUpdated], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
