<?php

namespace Fazzinipierluigi\AsgardCRM\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One field's value change, always written by Fazzinipierluigi\AsgardCRM\Services\
 * EntityChangeLogger — never updated or deleted afterwards, an
 * append-only audit trail. Several rows sharing the same
 * transaction_id came from the same save (one EntityRecordController
 * store()/update() call, or one WorkflowActionExecutor action).
 */
#[Fillable([
    'entity_slug',
    'entity_id',
    'transaction_id',
    'column_name',
    'field_label',
    'old_value',
    'new_value',
    'changed_by_user_id',
    'changed_by_label',
])]
class EntityFieldChange extends Model
{
    public const UPDATED_AT = null;

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(config('crm.user_model'), 'changed_by_user_id');
    }
}
