<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Workflows\TaskExecutors;

use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionPhase;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowInstance;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowToken;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\Contracts\TaskExecutor;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\WorkflowTokenTransitioner;
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
