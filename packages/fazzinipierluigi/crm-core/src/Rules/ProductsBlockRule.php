<?php

namespace Fazzinipierluigi\AsgardCRM\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Validates a "Blocco Prodotti" field's submitted value — a JSON-encoded
 * (or already-decoded) list of rows, each an associative array with the
 * block's fixed columns (product_id/name/description/quantity/unit_price/
 * subtotal) plus whatever extra columns the field was configured with
 * (same shape as TableFieldRule's own extra columns). product_id is
 * optional — a row may instead be a custom line item or a purely
 * descriptive row, identified by its own name/description — but when
 * present, or when quantity/unit_price are, the usual numeric rules
 * apply. Sibling of TableFieldRule, kept separate because the fixed
 * columns have their own numeric/existence rules a generic Table column
 * never needed.
 *
 * @phpstan-type ExtraColumn array{name: string, label?: string, type?: string, required?: bool}
 */
class ProductsBlockRule implements ValidationRule
{
    /**
     * @param  list<ExtraColumn>  $extraColumns
     * @param  string|null  $catalogTable  Physical table of the catalog entity, for a product_id existence check — null skips it (e.g. catalog entity not installed).
     */
    public function __construct(
        private readonly array $extraColumns = [],
        private readonly bool $requireAtLeastOne = false,
        private readonly ?string $catalogTable = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $rows = is_string($value) ? json_decode($value, true) : $value;

        if ($rows === null || $rows === '') {
            $rows = [];
        }

        if (! is_array($rows) || ! array_is_list($rows)) {
            $fail('Il campo :attribute non è un blocco prodotti valido.');

            return;
        }

        if ($this->requireAtLeastOne && count($rows) === 0) {
            $fail('Il campo :attribute richiede almeno un prodotto.');

            return;
        }

        $productIds = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $fail('Il campo :attribute non è un blocco prodotti valido.');

                return;
            }

            $productId = $row['product_id'] ?? null;
            $hasProduct = $productId !== null && $productId !== '';

            if ($hasProduct && ! is_numeric($productId)) {
                $fail('Ogni riga di :attribute deve avere un prodotto selezionato valido.');

                return;
            }

            if ($hasProduct) {
                $productIds[] = (int) $productId;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));

            if (! $hasProduct && $name === '' && $description === '') {
                $fail('Ogni riga di :attribute deve avere un prodotto selezionato oppure un nome o una descrizione.');

                return;
            }

            $quantity = $row['quantity'] ?? null;
            $quantityProvided = $quantity !== null && $quantity !== '';

            if (($hasProduct || $quantityProvided) && (! is_numeric($quantity) || (float) $quantity <= 0)) {
                $fail('Ogni riga di :attribute deve avere una quantità numerica maggiore di zero.');

                return;
            }

            $unitPrice = $row['unit_price'] ?? null;
            $unitPriceProvided = $unitPrice !== null && $unitPrice !== '';

            if (($hasProduct || $unitPriceProvided) && (! is_numeric($unitPrice) || (float) $unitPrice < 0)) {
                $fail('Ogni riga di :attribute deve avere un prezzo unitario numerico non negativo.');

                return;
            }

            foreach ($this->extraColumns as $column) {
                if (! ($column['required'] ?? false)) {
                    continue;
                }

                $cell = $row[$column['name']] ?? null;

                if ($cell === null || $cell === '') {
                    $fail("La colonna «{$column['label']}» è obbligatoria su ogni riga di :attribute.");

                    return;
                }
            }
        }

        if ($this->catalogTable !== null && $productIds !== []) {
            $existing = DB::table($this->catalogTable)->whereIn('id', array_unique($productIds))->whereNull('deleted_at')->pluck('id')->all();

            if (count($existing) !== count(array_unique($productIds))) {
                $fail('Uno o più prodotti selezionati in :attribute non esistono più.');
            }
        }
    }
}
