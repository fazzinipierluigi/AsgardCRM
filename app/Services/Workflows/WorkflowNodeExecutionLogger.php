<?php

namespace App\Services\Workflows;

use App\Enums\WorkflowNodeExecutionStatus;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNodeExecution;
use App\Models\WorkflowToken;

/**
 * Writes the per-node execution log the "Flussi" tab on an entity
 * record's detail page reads (see WorkflowNodeExecution). Called from
 * exactly two choke points every node type already passes through —
 * WorkflowEngine::processToken() (a token entering a node) and
 * WorkflowTokenTransitioner::traverse() (a token leaving one) — plus
 * a couple of node types that never traverse when they finish
 * (handleEnd(), a Semaforo's discarded branches) and the engine's own
 * failure path, which close a row directly.
 */
class WorkflowNodeExecutionLogger
{
    /**
     * Opens a new row for the token's current node, numbered by how
     * many times this (instance, node) pair has already been entered —
     * naturally covers cycles, whether revisited by the same token or a
     * different one spawned by a gateway.
     */
    public function enter(WorkflowInstance $instance, WorkflowToken $token): void
    {
        $iteration = WorkflowNodeExecution::where('workflow_instance_id', $instance->id)
            ->where('workflow_node_id', $token->workflow_node_id)
            ->count() + 1;

        WorkflowNodeExecution::create([
            'workflow_instance_id' => $instance->id,
            'workflow_node_id' => $token->workflow_node_id,
            'workflow_token_id' => $token->id,
            'iteration' => $iteration,
            'status' => WorkflowNodeExecutionStatus::Waiting,
            'entered_at' => now(),
            'via_edge_id' => $token->via_edge_id,
            'variables_snapshot' => $instance->variables ?? [],
        ]);
    }

    public function exit(WorkflowInstance $instance, WorkflowToken $token): void
    {
        $this->close($instance, $token, WorkflowNodeExecutionStatus::Completed);
    }

    public function fail(WorkflowInstance $instance, WorkflowToken $token): void
    {
        $this->close($instance, $token, WorkflowNodeExecutionStatus::Failed);
    }

    private function close(WorkflowInstance $instance, WorkflowToken $token, WorkflowNodeExecutionStatus $status): void
    {
        WorkflowNodeExecution::where('workflow_instance_id', $instance->id)
            ->where('workflow_token_id', $token->id)
            ->whereNull('exited_at')
            ->latest('id')
            ->first()
            ?->update(['status' => $status, 'exited_at' => now()]);
    }
}
