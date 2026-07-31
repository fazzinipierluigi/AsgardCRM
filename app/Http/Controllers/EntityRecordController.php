<?php

namespace App\Http\Controllers;

use App\Enums\EntityFieldType;
use App\Enums\WorkflowNodeExecutionStatus;
use App\Enums\WorkflowUserTaskStatus;
use App\Http\Requests\StoreEntityRecordRequest;
use App\Http\Requests\UpdateEntityRecordRequest;
use App\Models\Entity;
use App\Models\EntityField;
use App\Models\EntityFieldChange;
use App\Models\EntityRecord;
use App\Models\EntityRelation;
use App\Models\WorkflowEdge;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNode;
use App\Models\WorkflowNodeExecution;
use App\Models\WorkflowUserTask;
use App\Services\EntityChangeLogger;
use App\Services\EntityCodeGenerator;
use App\Services\EntityRecordAuthorizer;
use App\Services\EntityRelationLinkResolver;
use App\Services\EntityRelationResolver;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Frontend CRUD for an installed entity's own records. A single
 * generic controller serves every entity — the {entity:slug} route
 * parameter picks which one. Because of that, the `acl` middleware
 * (which keys permissions off Controller@action) can't tell entities
 * apart, so the flat entity_{slug}.* permission is checked by hand in
 * every action, on top of EntityRecordAuthorizer's per-row ownership
 * checks — see EntityInstaller for the permission keys.
 *
 * Tags/labels used in the allowlist below for RichText fields are kept
 * deliberately minimal (see sanitizeRichText()).
 */
class EntityRecordController extends Controller
{
    private const RICH_TEXT_ALLOWED_TAGS = '<b><i><u><strong><em><ul><ol><li><p><br><a>';

    public function __construct(
        private readonly EntityRecordAuthorizer $authorizer,
        private readonly EntityCodeGenerator $codeGenerator,
        private readonly EntityRelationResolver $relationResolver,
        private readonly EntityChangeLogger $changeLogger,
        private readonly EntityRelationLinkResolver $relationLinkResolver,
    ) {}

    /**
     * Show the records listing page for an entity.
     */
    public function index(Entity $entity): View
    {
        $this->authorizeAction($entity, 'index');
        $entity->load('tabs.cards.fields', 'listWidgets');

        return view('entities.index', [
            'entity' => $entity,
            'canCreate' => request()->user()->can("entity_{$entity->slug}.create"),
            'relationLookups' => $this->relationLookupsForColumns($entity),
            'buttonWidgets' => $entity->listWidgets->where('type', 'button')->where('is_active', true),
            'displayWidgets' => $entity->listWidgets->whereIn('type', ['counter', 'chart'])->where('is_active', true),
        ]);
    }

    /**
     * Serve the server-side datatable request for an entity's records.
     */
    public function data(Request $request, Entity $entity): JsonResponse
    {
        $this->authorizeAction($entity, 'index');

        $fields = $entity->allFields()->reject(fn (EntityField $f) => $f->type->isAction());
        $columns = array_merge(['id', 'user_id', 'created_at'], $fields->map(fn (EntityField $f) => $this->columnFor($f))->all());

        $records = EntityRecord::forEntity($entity)->newQuery()->with('owner')->select($columns);
        $this->authorizer->scopeQuery($records, $request->user(), $entity);

        $relationLabels = $fields->filter(fn (EntityField $f) => $f->type === EntityFieldType::Relation)
            ->mapWithKeys(fn (EntityField $f) => [$f->column_name => $this->relationResolver->labelsForField($f)]);

        $source = new EloquentSource;
        $source->apply($records, $request, null, []);

        return $source->getResponse(function (EntityRecord $record) use ($fields, $relationLabels, $request, $entity) {
            $row = [
                'id' => $record->id,
                'owner' => $record->owner?->name,
                'created_at' => $record->created_at->format('d/m/Y H:i'),
                'can_edit' => $this->authorizer->canEdit($request->user(), $entity, $record->user_id),
                'can_delete' => $this->authorizer->canDelete($request->user(), $entity, $record->user_id),
            ];

            foreach ($fields as $field) {
                $row[$field->column_name] = $this->displayValue($field, $record, $relationLabels);
            }

            return $row;
        });
    }

    /**
     * Show the form to create a new record.
     */
    public function create(Entity $entity): View
    {
        $this->authorizeAction($entity, 'create');

        return view('entities.create', [
            'entity' => $entity->load('tabs.cards.fields'),
            'record' => null,
            'relationOptions' => $this->relationOptionsForEntity($entity),
            'fieldConditions' => $this->fieldConditionsPayload($entity),
        ]);
    }

    /**
     * Persist a new record, owned by the current user.
     */
    public function store(StoreEntityRecordRequest $request, Entity $entity): RedirectResponse
    {
        $this->authorizeAction($entity, 'create');

        $attributes = $this->prepareAttributes($entity, $request, generateCodes: true);
        $attributes['user_id'] = $request->user()->id;

        $record = EntityRecord::forEntity($entity)->newQuery()->create($attributes);
        $this->changeLogger->logCreated($entity, $record, $attributes, $request->user());

        return redirect()->route('entities.index', $entity)->with('status', 'record-created');
    }

    /**
     * Show the form to edit an existing record.
     */
    public function edit(Entity $entity, int $record): View
    {
        $this->authorizeAction($entity, 'edit');
        $recordModel = $this->findRecordOrFail($entity, $record);
        $this->authorizeRow($entity, $recordModel, 'edit');

        $canViewWorkflows = request()->user()->can("entity_{$entity->slug}.workflows");

        return view('entities.edit', [
            'entity' => $entity->load('tabs.cards.fields'),
            'record' => $recordModel,
            'relationOptions' => $this->relationOptionsForEntity($entity),
            'workflowTasks' => $this->pendingWorkflowTasks($entity, $recordModel),
            'changeTransactions' => $this->changeTransactions($entity, $recordModel),
            'canViewWorkflows' => $canViewWorkflows,
            'workflowInstances' => $canViewWorkflows ? $this->workflowInstancesForRecord($entity, $recordModel) : collect(),
            'entityRelations' => $this->entityRelationsForRecord($entity, $recordModel),
            'fieldConditions' => $this->fieldConditionsPayload($entity),
        ]);
    }

    /**
     * Every workflow instance ever started against this exact record —
     * unlike pendingWorkflowTasks(), not limited to pending Task utente
     * steps flagged show_in_entity_detail: the "Flussi" tab wants every
     * instance regardless of status or node configuration.
     *
     * @return Collection<int, WorkflowInstance>
     */
    private function workflowInstancesForRecord(Entity $entity, EntityRecord $record): Collection
    {
        return WorkflowInstance::where('entity_slug', $entity->slug)
            ->where('entity_id', $record->getKey())
            ->with('workflow')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Every EntityRelation this entity is a side of, paired with how
     * many target records are currently linked to this specific
     * record — feeds the "Relazioni" sidebar card (see
     * resources/views/entities/edit.blade.php).
     *
     * @return Collection<int, array{relation: EntityRelation, targetEntity: Entity, count: int}>
     */
    private function entityRelationsForRecord(Entity $entity, EntityRecord $record): Collection
    {
        return $this->relationLinkResolver->relationsForEntity($entity)->map(fn (EntityRelation $relation) => [
            'relation' => $relation,
            'targetEntity' => $this->relationLinkResolver->targetEntityFor($relation, $entity),
            'count' => $this->relationLinkResolver->countLinks($relation, $entity, $record->getKey()),
        ]);
    }

    /**
     * The entity's conditional-field rules, reshaped for
     * resources/js/entity-field-conditions.js: each rule as raw
     * JsonLogic plus the list of fields it manages, keyed by the
     * field's physical form column name (matching what
     * _field_input.blade.php names its input) rather than the field
     * id — the client evaluator only ever deals in column names, both
     * for the rule's own {"var": ...} references and its targets.
     *
     * @return array<int, array{rule: mixed, targets: array<int, array{column: string, visible: bool, readonly: bool, required: bool}>}>
     */
    private function fieldConditionsPayload(Entity $entity): array
    {
        return $entity->fieldConditions()->with('targets.field')->get()
            ->map(fn ($condition) => [
                'rule' => $condition->rule,
                'targets' => $condition->targets->map(fn ($target) => [
                    'column' => $this->columnFor($target->field),
                    'visible' => $target->visible,
                    'readonly' => $target->readonly,
                    'required' => $target->required,
                ])->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The read-only graph + per-node execution log the "Flussi" tab's
     * viewer renders for one workflow instance bound to this record —
     * see WorkflowNodeExecutionLogger for how the log is written.
     */
    public function workflowInstanceGraph(Entity $entity, int $record, WorkflowInstance $workflowInstance): JsonResponse
    {
        $this->authorizeAction($entity, 'workflows');

        abort_unless($workflowInstance->entity_slug === $entity->slug && (int) $workflowInstance->entity_id === $record, 404);

        $version = $workflowInstance->workflowVersion()->with('nodes', 'edges')->firstOrFail();

        $executions = WorkflowNodeExecution::where('workflow_instance_id', $workflowInstance->id)->orderBy('iteration')->get();
        $executionsByNode = $executions->groupBy('workflow_node_id');
        $executedEdgeIds = $executions->pluck('via_edge_id')->filter()->unique();
        $userTasksByToken = WorkflowUserTask::where('workflow_instance_id', $workflowInstance->id)->get()->keyBy('workflow_token_id');

        return response()->json([
            'instance' => [
                'id' => $workflowInstance->id,
                'status' => $workflowInstance->status->value,
                'started_at' => $workflowInstance->started_at?->format('d/m/Y H:i'),
                'ended_at' => $workflowInstance->ended_at?->format('d/m/Y H:i'),
                'error_message' => $workflowInstance->error_message,
            ],
            'nodes' => $version->nodes->map(function (WorkflowNode $node) use ($executionsByNode) {
                $rows = $executionsByNode->get($node->id, collect());
                $waiting = $rows->contains(fn (WorkflowNodeExecution $e) => $e->status === WorkflowNodeExecutionStatus::Waiting);

                return [
                    'id' => $node->id,
                    'type' => $node->type->value,
                    'name' => $node->name,
                    'pos_x' => $node->pos_x,
                    'pos_y' => $node->pos_y,
                    'status' => $waiting ? 'waiting' : ($rows->isNotEmpty() ? 'completed' : 'none'),
                ];
            })->values(),
            'edges' => $version->edges->map(fn (WorkflowEdge $edge) => [
                'id' => $edge->id,
                'source_id' => $edge->source_node_id,
                'target_id' => $edge->target_node_id,
                'label' => $edge->label,
                'executed' => $executedEdgeIds->contains($edge->id),
            ])->values(),
            'logs' => $executionsByNode->map(fn ($rows) => $rows->map(function (WorkflowNodeExecution $execution) use ($userTasksByToken) {
                $userTask = $userTasksByToken->get($execution->workflow_token_id);

                return [
                    'iteration' => $execution->iteration,
                    'status' => $execution->status->value,
                    'entered_at' => $execution->entered_at->format('d/m/Y H:i:s'),
                    'exited_at' => $execution->exited_at?->format('d/m/Y H:i:s'),
                    'variables_snapshot' => $execution->variables_snapshot,
                    'user_task' => $userTask ? [
                        'status' => $userTask->status->value,
                        'form_data' => $userTask->form_data,
                        'completed_by' => $userTask->completer?->name,
                        'completed_at' => $userTask->completed_at?->format('d/m/Y H:i:s'),
                    ] : null,
                ];
            })->values()),
        ]);
    }

    /**
     * This record's change log, grouped by transaction (one save = one
     * group) — see App\Services\EntityChangeLogger. Ordering by id
     * descending keeps both the groups and the rows within each group
     * in reverse-chronological order, since Collection::groupBy()
     * preserves first-seen order.
     *
     * @return Collection<string, Collection<int, EntityFieldChange>>
     */
    private function changeTransactions(Entity $entity, EntityRecord $record): Collection
    {
        return EntityFieldChange::where('entity_slug', $entity->slug)
            ->where('entity_id', $record->getKey())
            ->with('changedByUser')
            ->orderByDesc('id')
            ->get()
            ->groupBy('transaction_id');
    }

    /**
     * Pending "Task utente" workflow steps bound to this exact record,
     * limited to ones whose node was configured to surface here (see
     * WorkflowNode.config.show_in_entity_detail).
     *
     * @return Collection<int, WorkflowUserTask>
     */
    private function pendingWorkflowTasks(Entity $entity, EntityRecord $record): Collection
    {
        return WorkflowUserTask::query()
            ->where('status', WorkflowUserTaskStatus::Pending->value)
            ->whereHas('instance', fn ($query) => $query->where('entity_slug', $entity->slug)->where('entity_id', $record->getKey()))
            ->whereHas('node', fn ($query) => $query->where('config->show_in_entity_detail', true))
            ->with('node')
            ->get();
    }

    /**
     * Update an existing record.
     */
    public function update(UpdateEntityRecordRequest $request, Entity $entity, int $record): RedirectResponse
    {
        $this->authorizeAction($entity, 'edit');
        $recordModel = $this->findRecordOrFail($entity, $record);
        $this->authorizeRow($entity, $recordModel, 'edit');

        $attributes = $this->prepareAttributes($entity, $request);
        $original = $recordModel->only(array_keys($attributes));

        $recordModel->update($attributes);
        $this->changeLogger->logUpdated($entity, $recordModel, $original, $attributes, $request->user());

        return redirect()->route('entities.index', $entity)->with('status', 'record-updated');
    }

    /**
     * Delete a record.
     */
    public function destroy(Entity $entity, int $record): RedirectResponse
    {
        $this->authorizeAction($entity, 'delete');
        $recordModel = $this->findRecordOrFail($entity, $record);
        $this->authorizeRow($entity, $recordModel, 'delete');

        $recordModel->delete();

        return redirect()->route('entities.index', $entity)->with('status', 'record-deleted');
    }

    private function findRecordOrFail(Entity $entity, int $recordId): EntityRecord
    {
        return EntityRecord::forEntity($entity)->newQuery()->findOrFail($recordId);
    }

    /**
     * 404 if the entity isn't installed yet (no table to read/write),
     * 403 if the user lacks the flat entity_{slug}.{action} permission.
     */
    private function authorizeAction(Entity $entity, string $action): void
    {
        if (! $entity->is_installed) {
            throw new NotFoundHttpException;
        }

        if (request()->user()->cannot("entity_{$entity->slug}.{$action}")) {
            abort(403);
        }
    }

    /**
     * 403 if the user's visibility level doesn't let them touch this
     * specific row (see EntityRecordAuthorizer for the rules).
     */
    private function authorizeRow(Entity $entity, EntityRecord $record, string $action): void
    {
        $allowed = match ($action) {
            'edit' => $this->authorizer->canEdit(request()->user(), $entity, $record->user_id),
            'delete' => $this->authorizer->canDelete(request()->user(), $entity, $record->user_id),
        };

        if (! $allowed) {
            abort(403);
        }
    }

    /**
     * Build the attributes to persist from the validated request,
     * filling in the checkbox fields the browser omits when unchecked
     * and sanitizing RichText HTML before it's ever stored. Generated
     * (Code) fields are only ever set on creation — $generateCodes is
     * false on update() so an existing record's code is never touched.
     *
     * @return array<string, mixed>
     */
    private function prepareAttributes(Entity $entity, Request $request, bool $generateCodes = false): array
    {
        $validated = $request->validated();
        $attributes = [];

        foreach ($entity->allFields() as $field) {
            $column = $this->columnFor($field);

            if ($field->type->isAction()) {
                continue;
            }

            if ($field->type->isGenerated()) {
                if ($generateCodes) {
                    $attributes[$column] = $this->codeGenerator->nextValue($field);
                }

                continue;
            }

            $attributes[$column] = match ($field->type) {
                EntityFieldType::Checkbox => $request->boolean($column),
                EntityFieldType::RichText => $this->sanitizeRichText($validated[$column] ?? null),
                EntityFieldType::Table => $validated[$column] ?: '[]',
                default => $validated[$column] ?? null,
            };
        }

        return $attributes;
    }

    private function sanitizeRichText(?string $value): ?string
    {
        return $value === null ? null : strip_tags($value, self::RICH_TEXT_ALLOWED_TAGS);
    }

    private function columnFor(EntityField $field): string
    {
        return $field->type === EntityFieldType::Relation ? "{$field->column_name}_id" : $field->column_name;
    }

    /**
     * Human-friendly display value for a field on a record, used by the
     * records datatable. Relation values are resolved through the
     * pre-fetched $relationLabels map rather than one query per row.
     *
     * @param  Collection<int, array<int|string, string>>  $relationLabels
     */
    private function displayValue(EntityField $field, EntityRecord $record, $relationLabels): mixed
    {
        $column = $this->columnFor($field);
        $value = $record->{$column};

        return match ($field->type) {
            EntityFieldType::Checkbox => (bool) $value,
            EntityFieldType::Select => $field->options[$value] ?? $value,
            EntityFieldType::Relation => $value === null ? null : ($relationLabels[$field->column_name][$value] ?? "#{$value}"),
            EntityFieldType::RichText => $value === null ? null : strip_tags((string) $value),
            EntityFieldType::Table => count(json_decode((string) $value, true) ?: []).' righe',
            default => $value,
        };
    }

    /**
     * All relation target options for every Relation field on an
     * entity, keyed by field column name — used to populate <select>s
     * on the create/edit forms.
     *
     * @return array<string, array<int|string, string>>
     */
    private function relationOptionsForEntity(Entity $entity): array
    {
        return $entity->allFields()
            ->filter(fn (EntityField $f) => $f->type === EntityFieldType::Relation)
            ->mapWithKeys(fn (EntityField $f) => [$f->column_name => $this->relationResolver->labelsForField($f)])
            ->all();
    }

    /**
     * Lookup filter options (raccoon-tables' filterLookup shape:
     * {value, name}[]) for every Relation field on an entity, keyed by
     * the field's physical column name — used by the records grid's
     * filter bar so a relation column filters via a label dropdown
     * instead of a free-text box against a raw foreign id.
     *
     * @return array<string, array<int, array{value: int|string, name: string}>>
     */
    private function relationLookupsForColumns(Entity $entity): array
    {
        return $entity->allFields()
            ->filter(fn (EntityField $f) => $f->type === EntityFieldType::Relation)
            ->mapWithKeys(function (EntityField $f) {
                $options = collect($this->relationResolver->labelsForField($f))
                    ->map(fn (string $name, int|string $value) => ['value' => $value, 'name' => $name])
                    ->values()
                    ->all();

                return [$this->columnFor($f) => $options];
            })
            ->all();
    }
}
