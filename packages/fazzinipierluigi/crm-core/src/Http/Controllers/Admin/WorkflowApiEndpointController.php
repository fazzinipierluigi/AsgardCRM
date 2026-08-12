<?php

namespace Fazzinipierluigi\CrmCore\Http\Controllers\Admin;

use Fazzinipierluigi\CrmCore\Http\Controllers\Controller;
use Fazzinipierluigi\CrmCore\Http\Requests\Admin\StoreWorkflowApiEndpointRequest;
use Fazzinipierluigi\CrmCore\Http\Requests\Admin\UpdateWorkflowApiEndpointRequest;
use Fazzinipierluigi\CrmCore\Models\Workflow;
use Fazzinipierluigi\CrmCore\Models\WorkflowApiEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin CRUD for reusable API endpoints the "Assegna variabile da API"
 * workflow action can call — global (workflow_id null) or scoped to a
 * single workflow. Same encrypted-config + "blank secret keeps the
 * previous one" pattern as ConnectorController.
 */
class WorkflowApiEndpointController extends Controller
{
    private const CONFIG_FIELDS = ['auth_type', 'token', 'username', 'password', 'header_name', 'header_value'];

    public function index(): View
    {
        return view('crm::admin.workflow-api-endpoints.index', [
            'endpoints' => WorkflowApiEndpoint::with('workflow')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('crm::admin.workflow-api-endpoints.create', [
            'endpoint' => null,
            'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreWorkflowApiEndpointRequest $request): RedirectResponse
    {
        WorkflowApiEndpoint::create([
            'workflow_id' => $request->validated('workflow_id'),
            'name' => $request->validated('name'),
            'base_url' => $request->validated('base_url'),
            'config' => $request->only(self::CONFIG_FIELDS),
        ]);

        return redirect()->route('admin.api-endpoints.index')->with('status', 'api-endpoint-created');
    }

    public function edit(WorkflowApiEndpoint $apiEndpoint): View
    {
        return view('crm::admin.workflow-api-endpoints.edit', [
            'endpoint' => $apiEndpoint,
            'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateWorkflowApiEndpointRequest $request, WorkflowApiEndpoint $apiEndpoint): RedirectResponse
    {
        $config = $request->only(self::CONFIG_FIELDS);

        foreach (['token', 'password', 'header_value'] as $secret) {
            if (empty($config[$secret])) {
                $config[$secret] = $apiEndpoint->config[$secret] ?? null;
            }
        }

        $apiEndpoint->update([
            'workflow_id' => $request->validated('workflow_id'),
            'name' => $request->validated('name'),
            'base_url' => $request->validated('base_url'),
            'config' => $config,
        ]);

        return redirect()->route('admin.api-endpoints.index')->with('status', 'api-endpoint-updated');
    }

    public function destroy(WorkflowApiEndpoint $apiEndpoint): RedirectResponse
    {
        $apiEndpoint->delete();

        return redirect()->route('admin.api-endpoints.index')->with('status', 'api-endpoint-deleted');
    }
}
