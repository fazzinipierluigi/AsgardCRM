<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\EntityRecord;
use App\Models\EntityRelation;
use App\Models\EntityRelationLink;
use App\Services\EntityRelationLinkResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Backs the "Relazioni" sidebar card + full-page sheet on a record's
 * detail page (see resources/views/entities/edit.blade.php and
 * resources/js/entity-relations.js): lists/attaches/detaches the
 * target-entity records linked to one specific record through an
 * EntityRelation. Same manual permission pattern as
 * EntityRecordController — a single generic controller shared by
 * every entity can't be told apart by the `acl` middleware, so the
 * flat entity_{slug}.edit permission is checked by hand, gating both
 * viewing and mutating a record's relations together with its own
 * fields.
 */
class EntityRelationLinkController extends Controller
{
    public function __construct(private readonly EntityRelationLinkResolver $resolver) {}

    /**
     * Plain JSON array (not raccoon-tables' paginated grid format — the
     * sheet renders it with a small hand-rolled table, see
     * resources/js/entity-relations.js) of every target-entity record
     * currently linked to this record for this relation.
     */
    public function data(Entity $entity, int $record, EntityRelation $relation): JsonResponse
    {
        $this->authorizeAndFindRelation($entity, $record, $relation);

        $targetEntity = $this->resolver->targetEntityFor($relation, $entity);
        $ownColumn = $relation->entity_a_id === $entity->id ? 'entity_a_record_id' : 'entity_b_record_id';
        $otherColumn = $ownColumn === 'entity_a_record_id' ? 'entity_b_record_id' : 'entity_a_record_id';
        $links = $this->resolver->linksFor($relation, $entity, $record);
        $labels = $this->resolver->labelsFor($targetEntity, $links->pluck($otherColumn)->all());

        return response()->json($links->map(fn (EntityRelationLink $link) => [
            'link_id' => $link->id,
            'record_id' => $link->{$otherColumn},
            'label' => $labels[$link->{$otherColumn}] ?? "#{$link->{$otherColumn}}",
        ])->values());
    }

    public function options(Request $request, Entity $entity, int $record, EntityRelation $relation): JsonResponse
    {
        $this->authorizeAndFindRelation($entity, $record, $relation);

        return response()->json(
            $this->resolver->availableOptions($relation, $entity, $record, $request->string('q')->value() ?: null)
        );
    }

    public function attach(Request $request, Entity $entity, int $record, EntityRelation $relation): JsonResponse
    {
        $this->authorizeAndFindRelation($entity, $record, $relation);

        $request->validate([
            'target_record_id' => ['required', 'integer'],
        ]);

        $targetEntity = $this->resolver->targetEntityFor($relation, $entity);
        $targetExists = EntityRecord::forEntity($targetEntity)->newQuery()->whereKey($request->integer('target_record_id'))->exists();
        abort_unless($targetExists, 404);

        $this->resolver->attach($relation, $entity, $record, $request->integer('target_record_id'));

        return response()->json(['message' => 'Relazione aggiunta.']);
    }

    public function detach(Entity $entity, int $record, EntityRelation $relation, EntityRelationLink $link): JsonResponse
    {
        $this->authorizeAndFindRelation($entity, $record, $relation);

        $ownColumn = $relation->entity_a_id === $entity->id ? 'entity_a_record_id' : 'entity_b_record_id';
        abort_unless($link->entity_relation_id === $relation->id && $link->{$ownColumn} === $record, 404);

        $link->delete();

        return response()->json(['message' => 'Relazione rimossa.']);
    }

    /**
     * 404 if the entity isn't installed or the relation isn't one of
     * its own, 403 if the user lacks edit rights on the record's
     * entity — the same gate EntityRecordController::edit() applies,
     * since managing relations is part of editing a record.
     */
    private function authorizeAndFindRelation(Entity $entity, int $record, EntityRelation $relation): void
    {
        if (! $entity->is_installed) {
            throw new NotFoundHttpException;
        }

        if (request()->user()->cannot("entity_{$entity->slug}.edit")) {
            abort(403);
        }

        abort_unless($this->resolver->belongsToEntity($relation, $entity), 404);
        abort_unless(EntityRecord::forEntity($entity)->newQuery()->whereKey($record)->exists(), 404);
    }
}
