<?php

namespace Fazzinipierluigi\AsgardCRM\Services;

use Fazzinipierluigi\AsgardCRM\Models\EntityField;
use Illuminate\Support\Facades\DB;

/**
 * Generates the next value for a Code field: its configured prefix
 * (entity_fields.options['prefix']) plus an atomically-incremented
 * per-field sequence number (entity_fields.sequence), no padding.
 */
class EntityCodeGenerator
{
    public function nextValue(EntityField $field): string
    {
        return DB::transaction(function () use ($field) {
            $current = EntityField::where('id', $field->id)->lockForUpdate()->value('sequence');

            EntityField::where('id', $field->id)->update(['sequence' => $current + 1]);

            $prefix = $field->options['prefix'] ?? '';

            return "{$prefix}{$current}";
        });
    }
}
