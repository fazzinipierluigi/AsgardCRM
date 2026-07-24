<?php

namespace App\Enums;

enum WorkflowActionType: string
{
    case SetVariable = 'set_variable';
    case AssignEntityToVariable = 'assign_entity_to_variable';
    case SendEmail = 'send_email';
    case UpdateEntity = 'update_entity';
    case CreateEntity = 'create_entity';
    case AssignVariableFromSql = 'assign_variable_from_sql';
    case AssignVariableFromApi = 'assign_variable_from_api';
    case FetchEntity = 'fetch_entity';

    public function label(): string
    {
        return match ($this) {
            self::SetVariable => 'Assegna valore a una variabile',
            self::AssignEntityToVariable => 'Assegna entità a una variabile',
            self::SendEmail => 'Invia email',
            self::UpdateEntity => 'Aggiorna entità',
            self::CreateEntity => 'Crea entità',
            self::AssignVariableFromSql => 'Assegna valore variabile da SQL',
            self::AssignVariableFromApi => 'Assegna valore variabile da API',
            self::FetchEntity => 'Preleva entità',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
