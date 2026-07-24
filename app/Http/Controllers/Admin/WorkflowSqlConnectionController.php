<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WorkflowSqlConnectionRequest;
use App\Models\Workflow;
use App\Models\WorkflowSqlConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin CRUD for reusable SQL connections the "Assegna variabile da SQL"
 * workflow action can run a read-only query against — global
 * (workflow_id null) or scoped to a single workflow. Same encrypted-
 * config + "blank password keeps the previous one" pattern as
 * ConnectorController.
 */
class WorkflowSqlConnectionController extends Controller
{
    private const CONFIG_FIELDS = ['driver', 'host', 'port', 'database', 'username', 'password'];

    public function index(): View
    {
        return view('admin.workflow-sql-connections.index', [
            'connections' => WorkflowSqlConnection::with('workflow')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.workflow-sql-connections.create', [
            'connection' => null,
            'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(WorkflowSqlConnectionRequest $request): RedirectResponse
    {
        WorkflowSqlConnection::create([
            'workflow_id' => $request->validated('workflow_id'),
            'name' => $request->validated('name'),
            'config' => $request->only(self::CONFIG_FIELDS),
        ]);

        return redirect()->route('admin.sql-connections.index')->with('status', 'sql-connection-created');
    }

    public function edit(WorkflowSqlConnection $sqlConnection): View
    {
        return view('admin.workflow-sql-connections.edit', [
            'connection' => $sqlConnection,
            'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(WorkflowSqlConnectionRequest $request, WorkflowSqlConnection $sqlConnection): RedirectResponse
    {
        $config = $request->only(self::CONFIG_FIELDS);

        if (empty($config['password'])) {
            $config['password'] = $sqlConnection->config['password'] ?? null;
        }

        $sqlConnection->update([
            'workflow_id' => $request->validated('workflow_id'),
            'name' => $request->validated('name'),
            'config' => $config,
        ]);

        return redirect()->route('admin.sql-connections.index')->with('status', 'sql-connection-updated');
    }

    public function destroy(WorkflowSqlConnection $sqlConnection): RedirectResponse
    {
        $sqlConnection->delete();

        return redirect()->route('admin.sql-connections.index')->with('status', 'sql-connection-deleted');
    }
}
