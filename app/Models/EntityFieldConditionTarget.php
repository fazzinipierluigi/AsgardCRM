<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One field "managed" by an EntityFieldCondition — its existence is
 * the "Gestione" checkbox from the admin condition form (see
 * Admin\EntityFieldConditionController): an entity field with no row
 * here for a given condition is left untouched while that condition's
 * rule is true.
 */
#[Fillable(['entity_field_condition_id', 'entity_field_id', 'visible', 'readonly', 'required'])]
class EntityFieldConditionTarget extends Model
{
    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'readonly' => 'boolean',
            'required' => 'boolean',
        ];
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(EntityFieldCondition::class, 'entity_field_condition_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(EntityField::class, 'entity_field_id');
    }
}
