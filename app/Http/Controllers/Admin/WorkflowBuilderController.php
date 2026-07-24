<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WorkflowActionPhase;
use App\Enums\WorkflowActionType;
use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowStartOccurrence;
use App\Enums\WorkflowTimerUnit;
use App\Enums\WorkflowTriggerType;
use App\Enums\WorkflowVariableType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWorkflowGraphRequest;
use App\Models\Entity;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowApiEndpoint;
use App\Models\WorkflowSqlConnection;
use App\Services\Workflows\WorkflowGraphPersister;
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

        return view('admin.workflows.builder', [
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
            'users' => User::orderBy('name')->get(['id', 'name']),
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
