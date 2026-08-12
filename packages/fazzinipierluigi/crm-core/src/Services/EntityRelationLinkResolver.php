<?php

namespace Fazzinipierluigi\CrmCore\Services;

use Fazzinipierluigi\CrmCore\Enums\EntityFieldType;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityField;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use Fazzinipierluigi\CrmCore\Models\EntityRelation;
use Fazzinipierluigi\CrmCore\Models\EntityRelationLink;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves and mutates EntityRelation/EntityRelationLink data for a
 * given entity/record — the "which relations does this entity have",
 * "how many records are linked", "attach/detach a record" operations
 * shared by the admin relation builder and the record detail sidebar
 * card + full-page sheet (see resources/js/entity-relations.js).
 */
class EntityRelationLinkResolver
{
    /**
     * Every relation definition touching this entity, on either side.
     *
     * @return Collection<int, EntityRelation>
     */
    public function relationsForEntity(Entity $entity): Collection
    {
        return EntityRelation::where('entity_a_id', $entity->id)
            ->orWhere('entity_b_id', $entity->id)
            ->with('entityA', 'entityB')
            ->orderBy('name')
            ->get();
    }

    /**
     * True if $entity is one of the two sides of $relation — used to
     * 404/403 a request for a relation that doesn't belong to the
     * entity in the URL.
     */
    public function belongsToEntity(EntityRelation $relation, Entity $entity): bool
    {
        return $relation->entity_a_id === $entity->id || $relation->entity_b_id === $entity->id;
    }

    public function targetEntityFor(EntityRelation $relation, Entity $current): Entity
    {
        return $relation->otherEntity($current);
    }

    public function countLinks(EntityRelation $relation, Entity $current, int $recordId): int
    {
        return $this->linksQuery($relation, $current, $recordId)->count();
    }

    /**
     * @return Collection<int, EntityRelationLink>
     */
    public function linksFor(EntityRelation $relation, Entity $current, int $recordId): Collection
    {
        return $this->linksQuery($relation, $current, $recordId)->get();
    }

    /**
     * Target-entity records not yet linked to this record for this
     * relation, optionally filtered by a search term against the
     * target entity's first String field — used to populate the
     * "attach" Tom Select in the record detail sheet.
     *
     * @return array<int, array{id: int, text: string}>
     */
    public function availableOptions(EntityRelation $relation, Entity $current, int $recordId, ?string $search = null, int $limit = 200): array
    {
        $targetEntity = $this->targetEntityFor($relation, $current);
        $linkedIds = $this->linksFor($relation, $current, $recordId)
            ->pluck($this->otherColumn($relation, $current));

        $labelField = $this->labelFieldFor($targetEntity);
        $query = EntityRecord::forEntity($targetEntity)->newQuery()->whereNotIn('id', $linkedIds);

        if ($search !== null && $search !== '' && $labelField !== null) {
            $query->where($labelField->column_name, 'like', "%{$search}%");
        }

        return $query->orderBy('id', 'desc')->limit($limit)->get()
            ->map(fn (EntityRecord $record) => [
                'id' => $record->id,
                'text' => $labelField !== null ? ((string) $record->{$labelField->column_name} ?: "#{$record->id}") : "#{$record->id}",
            ])
            ->all();
    }

    /**
     * Attach a target record to this record for this relation.
     * firstOrCreate() no-ops if the pair is already linked, matching
     * the unique constraint on entity_relation_links.
     */
    public function attach(EntityRelation $relation, Entity $current, int $recordId, int $targetRecordId): EntityRelationLink
    {
        $isA = $relation->entity_a_id === $current->id;

        return EntityRelationLink::firstOrCreate([
            'entity_relation_id' => $relation->id,
            'entity_a_record_id' => $isA ? $recordId : $targetRecordId,
            'entity_b_record_id' => $isA ? $targetRecordId : $recordId,
        ]);
    }

    /**
     * Human label for the given target-entity record ids, keyed by id
     * — used to render the linked-records table in the sheet.
     *
     * @param  array<int, int>  $recordIds
     * @return array<int, string>
     */
    public function labelsFor(Entity $targetEntity, array $recordIds): array
    {
        if ($recordIds === []) {
            return [];
        }

        $labelField = $this->labelFieldFor($targetEntity);
        $columns = $labelField !== null ? ['id', $labelField->column_name] : ['id'];

        return EntityRecord::forEntity($targetEntity)->newQuery()->whereIn('id', $recordIds)->get($columns)
            ->mapWithKeys(fn (EntityRecord $r) => [$r->id => ($labelField !== null ? $r->{$labelField->column_name} : null) ?: "#{$r->id}"])
            ->all();
    }

    private function labelFieldFor(Entity $entity): ?EntityField
    {
        return $entity->allFields()->first(fn (EntityField $f) => $f->type === EntityFieldType::String);
    }

    private function otherColumn(EntityRelation $relation, Entity $current): string
    {
        return $relation->entity_a_id === $current->id ? 'entity_b_record_id' : 'entity_a_record_id';
    }

    private function ownColumn(EntityRelation $relation, Entity $current): string
    {
        return $relation->entity_a_id === $current->id ? 'entity_a_record_id' : 'entity_b_record_id';
    }

    private function linksQuery(EntityRelation $relation, Entity $current, int $recordId)
    {
        return EntityRelationLink::where('entity_relation_id', $relation->id)
            ->where($this->ownColumn($relation, $current), $recordId);
    }
}
