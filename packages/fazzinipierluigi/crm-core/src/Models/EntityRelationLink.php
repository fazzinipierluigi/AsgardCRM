<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One concrete link between an entity_a record and an entity_b record
 * of its EntityRelation. entity_a_record_id/entity_b_record_id are
 * plain unsignedBigInteger, not foreign keys — like a Relation field's
 * own id column (see EntitySchemaBuilder), the target row lives in a
 * dynamic per-entity table the schema can't declare a static FK
 * against.
 */
#[Fillable(['entity_relation_id', 'entity_a_record_id', 'entity_b_record_id'])]
class EntityRelationLink extends Model
{
    public function relation(): BelongsTo
    {
        return $this->belongsTo(EntityRelation::class, 'entity_relation_id');
    }
}
