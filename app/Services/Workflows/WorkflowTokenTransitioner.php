<?php

namespace App\Services\Workflows;

use App\Enums\WorkflowActionPhase;
use App\Enums\WorkflowTimerStatus;
use App\Enums\WorkflowTokenStatus;
use App\Models\WorkflowEdge;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTimer;
use App\Models\WorkflowToken;
use Illuminate\Database\Eloquent\Model;

/**
 * The mechanics of moving a token: running an actionable's (a node or an
 * edge) actions for one phase, and repositioning a token onto an edge's
 * target node. This is deliberately kept separate from WorkflowEngine so
 * a TaskExecutor can perform the exact same transition a running engine
 * would, whether it executes inline or from a queued job resuming later
 * — the orchestration loop and the token-moving mechanics don't need to
 * know about each other.
 */
class WorkflowTokenTransitioner
{
    public function __construct(
        private readonly WorkflowActionExecutor $actions,
        private readonly WorkflowNodeExecutionLogger $executionLog,
    ) {}

    public function runActions(WorkflowInstance $instance, Model $actionable, WorkflowActionPhase $phase): void
    {
        foreach ($actionable->actionsFor($phase)->get() as $action) {
            $this->actions->execute($action, $instance);
        }
    }

    /**
     * Runs the edge's own before/after actions, then repositions the
     * token on the edge's target node (still Active, so the engine's
     * next advance() loop turn processes it).
     */
    public function traverse(WorkflowInstance $instance, WorkflowToken $token, WorkflowEdge $edge): void
    {
        $this->runActions($instance, $edge, WorkflowActionPhase::Before);
        $this->runActions($instance, $edge, WorkflowActionPhase::After);

        $this->executionLog->exit($instance, $token);
        $this->cancelPendingBoundaryTimers($token);

        $token->workflow_node_id = $edge->target_node_id;
        $token->via_edge_id = $edge->id;
        $token->status = WorkflowTokenStatus::Active;
        $token->save();
    }

    /**
     * Every path that moves a token forward funnels through here — the
     * single choke point for "the host of a Boundary Timer got there
     * first": whatever WorkflowTimer row was parked on this token (see
     * WorkflowEngine::attachBoundaryTimerIfAny()) is now moot. A no-op
     * when there isn't one, and equally a no-op for the boundary firing
     * itself (WorkflowEngine::fireBoundaryTimer() already flips its own
     * timer to Fired before calling traverse()).
     */
    private function cancelPendingBoundaryTimers(WorkflowToken $token): void
    {
        WorkflowTimer::where('workflow_token_id', $token->id)
            ->where('status', WorkflowTimerStatus::Pending->value)
            ->update(['status' => WorkflowTimerStatus::Cancelled->value]);
    }
}
