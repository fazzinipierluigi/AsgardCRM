<?php

namespace Fazzinipierluigi\AsgardCRM\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a Table field's submitted value — a JSON-encoded (or
 * already-decoded) list of rows, each an associative array keyed by
 * column name. Shared by entity records (BuildsEntityFieldRules) and
 * workflow "Task utente" form submissions (WorkflowUserTaskController),
 * which have no Eloquent/entity dependency in common otherwise.
 *
 * @phpstan-type TableColumn array{name: string, label?: string, type?: string, required?: bool}
 */
class TableFieldRule implements ValidationRule
{
    /**
     * @param  list<TableColumn>  $columns
     * @param  bool  $rowRequired  Whether at least one row must be present.
     */
    public function __construct(
        private readonly array $columns,
        private readonly bool $rowRequired = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rows = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($rows) || ! array_is_list($rows)) {
            $fail('Il campo :attribute non è una tabella valida.');

            return;
        }

        if ($this->rowRequired && count($rows) === 0) {
            $fail('Il campo :attribute richiede almeno una riga.');

            return;
        }

        $requiredColumns = array_filter($this->columns, fn (array $column) => $column['required'] ?? false);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $fail('Il campo :attribute non è una tabella valida.');

                return;
            }

            foreach ($requiredColumns as $column) {
                $cell = $row[$column['name']] ?? null;

                if ($cell === null || $cell === '') {
                    $fail("La colonna «{$column['label']}» è obbligatoria su ogni riga di :attribute.");

                    return;
                }
            }
        }
    }
}
