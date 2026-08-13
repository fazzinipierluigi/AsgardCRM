<?php

namespace Fazzinipierluigi\AsgardCRM\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A rule (JsonLogic, evaluated client-side against the record form's
 * current values — see resources/js/entity-field-conditions.js) that
 * toggles visible/readonly/required on a set of the entity's own
 * fields (its `targets`) while true. Purely a create/edit-form UX
 * concern: no server-side enforcement of visible/readonly/required
 * happens from this — StoreEntityRecordRequest/UpdateEntityRecordRequest
 * validate fields the same regardless of any condition's state.
 */
#[Fillable(['entity_id', 'name', 'rule', 'position'])]
class EntityFieldCondition extends Model
{
    protected function casts(): array
    {
        return [
            'rule' => 'array',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(EntityFieldConditionTarget::class);
    }
}
