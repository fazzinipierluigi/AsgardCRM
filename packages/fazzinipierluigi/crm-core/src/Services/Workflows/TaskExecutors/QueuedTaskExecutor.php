<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Workflows\TaskExecutors;

use Fazzinipierluigi\AsgardCRM\Enums\WorkflowTokenStatus;
use Fazzinipierluigi\AsgardCRM\Jobs\Workflows\ExecuteServiceTaskJob;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowInstance;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowToken;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\Contracts\TaskExecutor;

/**
 * Parks the token and hands the activity off to Laravel's queue instead
 * of running it inline. Which backend actually processes the job is a
 * matter of config('queue.default') alone — the sync/database driver
 * needs no external service, and switching to Redis/SQS/a broker is a
 * .env change, not a code change.
 */
class QueuedTaskExecutor implements TaskExecutor
{
    public function execute(WorkflowNode $node, WorkflowInstance $instance, WorkflowToken $token): void
    {
        $token->status = WorkflowTokenStatus::WaitingActivity;
        $token->save();

        ExecuteServiceTaskJob::dispatch($node, $instance, $token);
    }
}
