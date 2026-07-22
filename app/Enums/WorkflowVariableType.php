<?php

namespace App\Enums;

enum WorkflowVariableType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Float = 'float';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case Object = 'object';
    case Array = 'array';

    public function label(): string
    {
        return match ($this) {
            self::String => 'Stringa',
            self::Integer => 'Intero',
            self::Float => 'Float',
            self::Boolean => 'Booleano',
            self::Date => 'Data',
            self::DateTime => 'Data e ora',
            self::Object => 'Oggetto',
            self::Array => 'Array',
        };
    }

    /**
     * Cast a raw (scalar/array) value to this variable's PHP type, e.g.
     * when reading a stored default_value or an expression's result.
     */
    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::String => (string) $value,
            self::Integer => (int) $value,
            self::Float => (float) $value,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::Date, self::DateTime => $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : (string) $value,
            self::Object, self::Array => is_string($value) ? json_decode($value, true) : $value,
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
