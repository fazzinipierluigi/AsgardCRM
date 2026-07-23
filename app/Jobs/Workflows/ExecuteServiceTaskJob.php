<?php

namespace App\Jobs\Workflows;

use App\Enums\WorkflowActivityExecutionStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Models\WorkflowActivityExecution;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNode;
use App\Models\WorkflowToken;
use App\Services\Workflows\TaskExecutors\SyncTaskExecutor;
use App\Services\Workflows\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs a Task processo/script's activity out of band, then resumes the
 * instance — the same "run actions, traverse the edge" work
 * SyncTaskExecutor does in-process, just deferred to whatever queue
 * connection is configured. workflow_activity_executions is what stops a
 * redelivered/retried job from running the activity's actions (e.g. an
 * email, an entity creation) a second time.
 */
class ExecuteServiceTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public int $tries = 3;

    public function __construct(
        public readonly WorkflowNode $node,
        public readonly WorkflowInstance $instance,
        public readonly WorkflowToken $token,
    ) {}

    public function handle(SyncTaskExecutor $executor, WorkflowEngine $engine): void
    {
        $execution = WorkflowActivityExecution::firstOrCreate(
            ['workflow_token_id' => $this->token->id],
            ['status' => WorkflowActivityExecutionStatus::Pending],
        );

        if ($execution->status === WorkflowActivityExecutionStatus::Completed) {
            return;
        }

        $executor->execute($this->node, $this->instance, $this->token);

        $execution->update(['status' => WorkflowActivityExecutionStatus::Completed]);

        $engine->advance($this->instance->fresh());
    }

    public function failed(Throwable $exception): void
    {
        $this->instance->update([
            'status' => WorkflowInstanceStatus::Failed,
            'error_message' => $exception->getMessage(),
            'ended_at' => now(),
        ]);
    }
}
