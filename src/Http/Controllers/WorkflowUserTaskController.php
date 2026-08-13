<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers;

use Fazzinipierluigi\AsgardCRM\Enums\WorkflowUserTaskStatus;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowUserTask;
use Fazzinipierluigi\AsgardCRM\Rules\TableFieldRule;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\WorkflowActionExecutor;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\WorkflowEngine;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Lets any authenticated user see and complete the "Task utente" nodes
 * assigned to them — either directly (assigned_user_id) or through a
 * role they hold (assigned_role_id). A task with neither set is
 * unassigned and open to anyone, same as an unclaimed queue item.
 */
class WorkflowUserTaskController extends Controller
{
    public function index(): View
    {
        return view('crm::workflow-tasks.index');
    }

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();

        $tasks = WorkflowUserTask::query()
            ->where('status', WorkflowUserTaskStatus::Pending->value)
            ->where(function ($query) use ($user) {
                $query->where('assigned_user_id', $user->id)
                    ->orWhereIn('assigned_role_id', $user->getRoles()->pluck('id'))
                    ->orWhere(function ($query) {
                        $query->whereNull('assigned_user_id')->whereNull('assigned_role_id');
                    });
            })
            ->with(['node', 'instance.workflow']);

        $source = new EloquentSource;
        $source->apply($tasks, $request, null, []);

        return $source->getResponse(function (WorkflowUserTask $task) {
            return [
                'id' => $task->id,
                'workflow' => $task->instance->workflow->name,
                'node' => $task->node->name,
                'created_at' => $task->created_at->format('d/m/Y H:i'),
            ];
        });
    }

    public function edit(WorkflowUserTask $workflowUserTask): View
    {
        $this->authorizeAccess($workflowUserTask);

        return view('crm::workflow-tasks.edit', [
            'task' => $workflowUserTask,
            'fields' => $workflowUserTask->node->config['form_fields'] ?? [],
            'showInEntityDetail' => (bool) ($workflowUserTask->node->config['show_in_entity_detail'] ?? false),
        ]);
    }

    public function update(Request $request, WorkflowUserTask $workflowUserTask, WorkflowEngine $engine, WorkflowActionExecutor $actions): RedirectResponse
    {
        $this->authorizeAccess($workflowUserTask);

        if (in_array($workflowUserTask->status, [WorkflowUserTaskStatus::Completed, WorkflowUserTaskStatus::Expired], true)) {
            return redirect()->route('workflow-tasks.index');
        }

        $fields = $workflowUserTask->node->config['form_fields'] ?? [];
        $formData = [];
        foreach ($fields as $field) {
            if (($field['type'] ?? null) === 'table') {
                $request->validate([
                    $field['name'] => [new TableFieldRule($field['columns'] ?? [], false)],
                ]);

                $formData[$field['name']] = json_decode((string) $request->input($field['name'], '[]'), true) ?? [];

                continue;
            }

            $formData[$field['name']] = $request->input($field['name']);
        }

        $actions->lastRedirectUrl = null;
        $engine->completeUserTask($workflowUserTask, $formData, $request->user());

        if ($actions->lastRedirectUrl) {
            return redirect($actions->lastRedirectUrl)->with('status', 'workflow-task-completed');
        }

        return redirect()->route('workflow-tasks.index')->with('status', 'workflow-task-completed');
    }

    private function authorizeAccess(WorkflowUserTask $task): void
    {
        $user = request()->user();

        $allowed = $task->assigned_user_id === $user->id
            || ($task->assigned_role_id && $user->hasRole($task->assignedRole?->slug ?? ''))
            || (! $task->assigned_user_id && ! $task->assigned_role_id);

        if (! $allowed) {
            throw new AccessDeniedHttpException('Questo task non è assegnato a te.');
        }
    }
}
