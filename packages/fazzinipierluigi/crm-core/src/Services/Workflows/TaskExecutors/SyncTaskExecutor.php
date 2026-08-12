<?php

namespace Fazzinipierluigi\CrmCore\Services\Workflows\TaskExecutors;

use Fazzinipierluigi\CrmCore\Enums\WorkflowActionPhase;
use Fazzinipierluigi\CrmCore\Models\WorkflowInstance;
use Fazzinipierluigi\CrmCore\Models\WorkflowNode;
use Fazzinipierluigi\CrmCore\Models\WorkflowToken;
use Fazzinipierluigi\CrmCore\Services\Workflows\Contracts\TaskExecutor;
use Fazzinipierluigi\CrmCore\Services\Workflows\WorkflowTokenTransitioner;
use RuntimeException;

/**
 * The zero-infrastructure default: runs the node's actions and traverses
 * its token in-process, in the same request/command that reached it —
 * identical to how every node type already behaves. Also what
 * ExecuteServiceTaskJob calls once it actually runs, so the activity
 * logic itself exists in exactly one place regardless of transport.
 */
class SyncTaskExecutor implements TaskExecutor
{
    public function __construct(private readonly WorkflowTokenTransitioner $transitioner) {}

    public function execute(WorkflowNode $node, WorkflowInstance $instance, WorkflowToken $token): void
    {
        $this->transitioner->runActions($instance, $node, WorkflowActionPhase::Before);
        $this->transitioner->runActions($instance, $node, WorkflowActionPhase::After);

        $edge = $node->outgoingEdges()->first();

        if (! $edge) {
            throw new RuntimeException("Il nodo «{$node->name}» non ha un arco in uscita.");
        }

        $this->transitioner->traverse($instance, $token, $edge);
    }
}
