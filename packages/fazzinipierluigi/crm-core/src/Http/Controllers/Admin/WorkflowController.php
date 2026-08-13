<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin;

use Fazzinipierluigi\AsgardCRM\Http\Controllers\Controller;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\ImportWorkflowRequest;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\StoreWorkflowRequest;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\UpdateWorkflowRequest;
use Fazzinipierluigi\AsgardCRM\Models\Workflow;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowInstance;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\WorkflowEngine;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\WorkflowGraphPersister;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Admin CRUD for Workflows. The graph itself (nodes/edges/variables/
 * actions) is edited on the WorkflowBuilderController page — this
 * controller only handles the list, the lightweight name/description/
 * status form, deletion, manual start, and export/import.
 */
class WorkflowController extends Controller
{
    public function index(): View
    {
        return view('crm::admin.workflows.index');
    }

    public function data(Request $request): JsonResponse
    {
        $workflows = Workflow::query()
            ->withCount('instances')
            ->select('id', 'name', 'description', 'is_active', 'created_at');

        $source = new EloquentSource;
        $source->apply($workflows, $request, null, ['name']);

        return $source->getResponse(function (Workflow $workflow) {
            return [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'description' => $workflow->description,
                'is_active' => $workflow->is_active,
                'instances_count' => $workflow->instances_count,
                'created_at' => $workflow->created_at->format('d/m/Y H:i'),
            ];
        });
    }

    public function create(): View
    {
        return view('crm::admin.workflows.create');
    }

    public function store(StoreWorkflowRequest $request): RedirectResponse
    {
        $workflow = Workflow::create([
            'name' => $request->string('name'),
            'description' => $request->string('description')->value() ?: null,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.workflows.builder.edit', $workflow);
    }

    public function edit(Workflow $workflow): RedirectResponse
    {
        return redirect()->route('admin.workflows.builder.edit', $workflow);
    }

    public function update(UpdateWorkflowRequest $request, Workflow $workflow): RedirectResponse
    {
        $workflow->update([
            'name' => $request->string('name'),
            'description' => $request->string('description')->value() ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.workflows.index')->with('status', 'workflow-updated');
    }

    public function destroy(Workflow $workflow): RedirectResponse
    {
        if ($workflow->instances()->whereIn('status', ['running', 'waiting'])->exists()) {
            return redirect()->route('admin.workflows.index')->with('error', 'Non è possibile eliminare un workflow con istanze in corso.');
        }

        $workflow->delete();

        return redirect()->route('admin.workflows.index')->with('status', 'workflow-deleted');
    }

    public function show(Workflow $workflow): View
    {
        return view('crm::admin.workflows.show', ['workflow' => $workflow]);
    }

    public function instancesData(Request $request, Workflow $workflow): JsonResponse
    {
        $instances = $workflow->instances()->getQuery()->select('id', 'workflow_id', 'status', 'entity_type', 'entity_id', 'started_at', 'ended_at', 'error_message');

        $source = new EloquentSource;
        $source->apply($instances, $request, null, []);

        return $source->getResponse(function (WorkflowInstance $instance) {
            return [
                'id' => $instance->id,
                'status' => $instance->status->label(),
                'entity_type' => $instance->entity_type,
                'started_at' => $instance->started_at?->format('d/m/Y H:i:s'),
                'ended_at' => $instance->ended_at?->format('d/m/Y H:i:s'),
                'error_message' => $instance->error_message,
            ];
        });
    }

    public function run(Workflow $workflow, WorkflowEngine $engine): RedirectResponse
    {
        try {
            $instance = $engine->start($workflow);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.workflows.show', $workflow)->with('error', $e->getMessage());
        }

        if ($instance === null) {
            return redirect()->route('admin.workflows.show', $workflow)->with('error', 'La condizione di avvio non è soddisfatta.');
        }

        return redirect()->route('admin.workflows.show', $workflow)->with('status', 'workflow-run-started');
    }

    public function export(Workflow $workflow, WorkflowGraphPersister $persister): JsonResponse
    {
        return response()->json($persister->toArray($workflow))
            ->header('Content-Disposition', 'attachment; filename="workflow-'.$workflow->id.'.json"');
    }

    public function importForm(): View
    {
        return view('crm::admin.workflows.import');
    }

    public function import(ImportWorkflowRequest $request, WorkflowGraphPersister $persister): RedirectResponse
    {
        $data = json_decode($request->file('file')->get(), true);

        if (! is_array($data)) {
            return back()->with('error', 'Il file non è un JSON valido.');
        }

        $workflow = Workflow::create([
            'name' => $data['name'] ?? 'Workflow importato',
            'created_by' => $request->user()->id,
        ]);

        try {
            $persister->replace($workflow, $data);
            $persister->publish($workflow);
        } catch (RuntimeException $e) {
            $workflow->delete();

            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.workflows.builder.edit', $workflow)->with('status', 'workflow-imported');
    }
}
