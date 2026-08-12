<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A many-to-many relation *definition* between two entities (entity_a
 * and entity_b — the pairing is unordered in meaning, "a"/"b" only
 * fixes a storage direction for EntityRelationLink's two record-id
 * columns). Not to be confused with an EntityField of type Relation,
 * which is a single belongsTo-style pointer stored on the entity's own
 * table — this is a separate many-to-many link, stored in
 * entity_relation_links, surfaced on both entities' record detail
 * pages via the "Relazioni" sidebar card.
 */
#[Fillable(['entity_a_id', 'entity_b_id', 'name'])]
class EntityRelation extends Model
{
    public function entityA(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_a_id');
    }

    public function entityB(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_b_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(EntityRelationLink::class);
    }

    /**
     * The other entity of the pair, given one side of it.
     */
    public function otherEntity(Entity $entity): Entity
    {
        return $this->entity_a_id === $entity->id ? $this->entityB : $this->entityA;
    }
}
