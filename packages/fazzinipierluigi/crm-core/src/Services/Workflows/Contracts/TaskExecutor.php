<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Workflows\Contracts;

use Fazzinipierluigi\AsgardCRM\Models\WorkflowInstance;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowToken;

/**
 * Runs a Task processo/script (ServiceTask) node's activity and advances
 * its token past it. WorkflowEngine never runs an activity itself — it
 * only asks whichever TaskExecutor the node is configured for to do so,
 * which is what lets the engine stay identical whether the activity runs
 * in-process (SyncTaskExecutor, the zero-infrastructure default) or is
 * handed off to a queue (QueuedTaskExecutor) — or, in the future, to any
 * other backend (a message broker, an external workflow runtime, ...)
 * behind the same interface, without the engine changing at all.
 */
interface TaskExecutor
{
    public function execute(WorkflowNode $node, WorkflowInstance $instance, WorkflowToken $token): void;
}
