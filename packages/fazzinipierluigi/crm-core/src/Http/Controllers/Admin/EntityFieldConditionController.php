<?php

namespace Fazzinipierluigi\CrmCore\Http\Controllers\Admin;

use Fazzinipierluigi\CrmCore\Http\Controllers\Controller;
use Fazzinipierluigi\CrmCore\Http\Requests\Admin\EntityFieldConditionRequest;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityField;
use Fazzinipierluigi\CrmCore\Models\EntityFieldCondition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin CRUD for an entity's conditional-field rules (see
 * Fazzinipierluigi\CrmCore\Models\EntityFieldCondition) — each rule's targets are always
 * fully replaced on save (delete-then-recreate, like
 * EntityBuilderController's non-installed tree replace) rather than
 * diffed, since a condition's target list is small and has no
 * physical column/schema tied to it to preserve.
 */
class EntityFieldConditionController extends Controller
{
    public function index(Entity $entity): View
    {
        return view('crm::admin.entities.conditions.index', [
            'entity' => $entity,
            'conditions' => $entity->fieldConditions,
        ]);
    }

    public function create(Entity $entity): View
    {
        return view('crm::admin.entities.conditions.form', $this->formData($entity));
    }

    public function store(EntityFieldConditionRequest $request, Entity $entity): RedirectResponse
    {
        DB::transaction(function () use ($request, $entity) {
            $condition = $entity->fieldConditions()->create([
                'name' => $request->validated('name'),
                'rule' => $request->decodedRule(),
                'position' => $entity->fieldConditions()->max('position') + 1,
            ]);

            $this->syncTargets($condition, $entity, $request->validated('fields', []));
        });

        return redirect()->route('admin.entities.conditions.index', $entity)->with('status', 'entity-condition-added');
    }

    public function edit(Entity $entity, EntityFieldCondition $condition): View
    {
        abort_unless($condition->entity_id === $entity->id, 404);

        return view('crm::admin.entities.conditions.form', [
            ...$this->formData($entity),
            'condition' => $condition->load('targets'),
        ]);
    }

    public function update(EntityFieldConditionRequest $request, Entity $entity, EntityFieldCondition $condition): RedirectResponse
    {
        abort_unless($condition->entity_id === $entity->id, 404);

        DB::transaction(function () use ($request, $condition, $entity) {
            $condition->update([
                'name' => $request->validated('name'),
                'rule' => $request->decodedRule(),
            ]);

            $this->syncTargets($condition, $entity, $request->validated('fields', []));
        });

        return redirect()->route('admin.entities.conditions.index', $entity)->with('status', 'entity-condition-updated');
    }

    public function destroy(Entity $entity, EntityFieldCondition $condition): RedirectResponse
    {
        abort_unless($condition->entity_id === $entity->id, 404);

        $condition->delete();

        return redirect()->route('admin.entities.conditions.index', $entity)->with('status', 'entity-condition-deleted');
    }

    /**
     * @param  array<string, mixed>  $fieldsInput  keyed by entity_field_id, each ['managed' => bool, 'visible' => bool, 'readonly' => bool, 'required' => bool]
     */
    private function syncTargets(EntityFieldCondition $condition, Entity $entity, array $fieldsInput): void
    {
        $validFieldIds = $entity->allFields()->pluck('id')->all();
        $condition->targets()->delete();

        foreach ($fieldsInput as $fieldId => $row) {
            $fieldId = (int) $fieldId;

            if (! in_array($fieldId, $validFieldIds, true) || ! filter_var($row['managed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $condition->targets()->create([
                'entity_field_id' => $fieldId,
                'visible' => filter_var($row['visible'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'readonly' => filter_var($row['readonly'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'required' => filter_var($row['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }

    /**
     * @return array{entity: Entity, fields: Collection<int, EntityField>}
     */
    private function formData(Entity $entity): array
    {
        return [
            'entity' => $entity,
            'fields' => $entity->allFields(),
        ];
    }
}
