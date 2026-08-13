<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin;

use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionPhase;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowNodeType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowStartOccurrence;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowTimerUnit;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowTriggerType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowVariableType;
use Fazzinipierluigi\AsgardCRM\Http\Controllers\Controller;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\UpdateWorkflowGraphRequest;
use Fazzinipierluigi\AsgardCRM\Models\Entity;
use Fazzinipierluigi\AsgardCRM\Models\Workflow;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowApiEndpoint;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowSqlConnection;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\WorkflowGraphPersister;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use RuntimeException;

/**
 * The drag-and-drop MaxGraph editor for a single workflow's graph.
 * edit() hands the page every option list the node/edge/action config
 * panels need; update() writes the whole graph into the current draft
 * version in one shot (see WorkflowGraphPersister) and always answers
 * in JSON, since the page saves via fetch() without navigating away.
 * publish() is the separate action that promotes that draft to live.
 */
class WorkflowBuilderController extends Controller
{
    public function edit(Workflow $workflow, WorkflowGraphPersister $persister): View
    {
        $entities = Entity::where('is_installed', true)->orderBy('name')->get();

        return view('crm::admin.workflows.builder', [
            'workflow' => $workflow,
            'graph' => $persister->toArray($workflow),
            'nodeTypes' => WorkflowNodeType::options(),
            'variableTypes' => WorkflowVariableType::options(),
            'actionTypes' => WorkflowActionType::options(),
            'actionPhases' => WorkflowActionPhase::options(),
            'triggerTypes' => WorkflowTriggerType::options(),
            'occurrenceOptions' => WorkflowStartOccurrence::options(),
            'timerUnits' => WorkflowTimerUnit::options(),
            'entities' => $entities->map(fn (Entity $entity) => [
                'slug' => $entity->slug,
                'name' => $entity->name,
                'fields' => $entity->allFields()->map(fn ($field) => [
                    'column_name' => $field->column_name,
                    'name' => $field->name,
                ])->values(),
            ])->values(),
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'users' => config('crm.user_model')::orderBy('name')->get(['id', 'name']),
            'otherWorkflows' => Workflow::where('id', '!=', $workflow->id)->orderBy('name')->get(['id', 'name']),
            'sqlConnections' => $this->scopedToWorkflow(WorkflowSqlConnection::query(), $workflow),
            'apiEndpoints' => $this->scopedToWorkflow(WorkflowApiEndpoint::query(), $workflow),
        ]);
    }

    /**
     * Global (workflow_id null) records plus any scoped to this one
     * workflow — the set of SQL connections/API endpoints its
     * "Assegna variabile da SQL/API" actions may pick from.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function scopedToWorkflow(Builder $query, Workflow $workflow): Collection
    {
        return $query->where(fn ($q) => $q->whereNull('workflow_id')->orWhere('workflow_id', $workflow->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function update(UpdateWorkflowGraphRequest $request, Workflow $workflow, WorkflowGraphPersister $persister): JsonResponse
    {
        try {
            $persister->replace($workflow, $request->validated());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'ok']);
    }

    public function publish(Workflow $workflow, WorkflowGraphPersister $persister): JsonResponse
    {
        try {
            $version = $persister->publish($workflow);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'ok', 'version' => $version->version]);
    }
}
