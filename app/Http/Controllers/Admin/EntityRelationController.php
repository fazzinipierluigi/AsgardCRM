<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EntityRelationRequest;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Services\EntityRelationLinkResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Admin CRUD for many-to-many relation definitions between two
 * entities (see App\Models\EntityRelation). $entity is always the "a"
 * side of a new relation — the admin picks the "b" side from every
 * other installed entity.
 */
class EntityRelationController extends Controller
{
    public function __construct(private readonly EntityRelationLinkResolver $resolver) {}

    public function index(Entity $entity): View
    {
        return view('admin.entities.relations.index', [
            'entity' => $entity,
            'relations' => $this->resolver->relationsForEntity($entity),
        ]);
    }

    public function create(Entity $entity): View
    {
        return view('admin.entities.relations.form', $this->formData($entity));
    }

    public function store(EntityRelationRequest $request, Entity $entity): RedirectResponse
    {
        EntityRelation::create([
            'entity_a_id' => $entity->id,
            'entity_b_id' => $request->validated('entity_b_id'),
            'name' => $request->validated('name'),
        ]);

        return redirect()->route('admin.entities.relations.index', $entity)->with('status', 'entity-relation-added');
    }

    public function edit(Entity $entity, EntityRelation $relation): View
    {
        abort_unless($this->resolver->belongsToEntity($relation, $entity), 404);

        return view('admin.entities.relations.form', [
            ...$this->formData($entity),
            'relation' => $relation,
            'otherEntityId' => $this->resolver->targetEntityFor($relation, $entity)->id,
        ]);
    }

    /**
     * $entity might be either side of an existing relation (a relation
     * created from the other entity's builder page still lists it
     * here — see EntityRelationLinkResolver::relationsForEntity()), so
     * the column that actually needs updating is whichever one isn't
     * $entity's own id, not always entity_b_id.
     */
    public function update(EntityRelationRequest $request, Entity $entity, EntityRelation $relation): RedirectResponse
    {
        abort_unless($this->resolver->belongsToEntity($relation, $entity), 404);

        $column = $relation->entity_a_id === $entity->id ? 'entity_b_id' : 'entity_a_id';

        $relation->update([
            $column => $request->validated('entity_b_id'),
            'name' => $request->validated('name'),
        ]);

        return redirect()->route('admin.entities.relations.index', $entity)->with('status', 'entity-relation-updated');
    }

    public function destroy(Entity $entity, EntityRelation $relation): RedirectResponse
    {
        abort_unless($this->resolver->belongsToEntity($relation, $entity), 404);

        $relation->delete();

        return redirect()->route('admin.entities.relations.index', $entity)->with('status', 'entity-relation-deleted');
    }

    /**
     * @return array{entity: Entity, otherEntities: Collection<int, Entity>}
     */
    private function formData(Entity $entity): array
    {
        return [
            'entity' => $entity,
            'otherEntities' => Entity::where('id', '!=', $entity->id)
                ->where('is_installed', true)
                ->orderBy('name')
                ->get(),
        ];
    }
}
