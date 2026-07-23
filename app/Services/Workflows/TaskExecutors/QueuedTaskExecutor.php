<?php

namespace App\Services\Workflows\TaskExecutors;

use App\Enums\WorkflowTokenStatus;
use App\Jobs\Workflows\ExecuteServiceTaskJob;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNode;
use App\Models\WorkflowToken;
use App\Services\Workflows\Contracts\TaskExecutor;

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
