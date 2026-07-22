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
use App\Models\Workflow;
use App\Services\Workflows\WorkflowGraphPersister;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use RuntimeException;

/**
 * The drag-and-drop MaxGraph editor for a single workflow's graph.
 * edit() hands the page every option list the node/edge/action config
 * panels need; update() replaces the whole graph in one shot (see
 * WorkflowGraphPersister) and always answers in JSON, since the page
 * saves via fetch() without navigating away.
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
            'otherWorkflows' => Workflow::where('id', '!=', $workflow->id)->orderBy('name')->get(['id', 'name']),
        ]);
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
}
