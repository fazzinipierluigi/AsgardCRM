<?php

namespace App\Console\Commands;

use App\Enums\WorkflowTimerStatus;
use App\Models\WorkflowTimer;
use App\Services\Workflows\WorkflowEngine;
use Illuminate\Console\Command;

/**
 * Fires every pending WorkflowTimer whose run_at has passed, resuming
 * the token parked on that Timer node.
 */
class FireDueWorkflowTimers extends Command
{
    protected $signature = 'workflows:fire-due-timers';

    protected $description = 'Fire every workflow timer whose run_at has passed';

    public function handle(WorkflowEngine $engine): int
    {
        $timers = WorkflowTimer::where('status', WorkflowTimerStatus::Pending->value)
            ->where('run_at', '<=', now())
            ->get();

        foreach ($timers as $timer) {
            $engine->fireTimer($timer);
        }

        $this->info("Fired {$timers->count()} timer(s).");

        return self::SUCCESS;
    }
}
