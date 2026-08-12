<?php

namespace Fazzinipierluigi\CrmCore\Console\Commands;

use Fazzinipierluigi\CrmCore\Enums\WorkflowNodeType;
use Fazzinipierluigi\CrmCore\Enums\WorkflowTriggerType;
use Fazzinipierluigi\CrmCore\Models\Workflow;
use Fazzinipierluigi\CrmCore\Services\Workflows\WorkflowEngine;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Throwable;

/**
 * Starts a new instance for every active workflow whose start node is
 * configured with the "Avvio via timer/cron" trigger and is due right
 * now — same isDue()/dedup shape as RunDueImporters, just keyed off
 * the start node's config instead of a dedicated schedule column.
 */
class RunDueWorkflows extends Command
{
    protected $signature = 'workflows:run-due';

    protected $description = 'Start a new instance for every active workflow whose cron trigger is due';

    public function handle(WorkflowEngine $engine): int
    {
        $workflows = Workflow::where('is_active', true)
            ->with('currentVersion.startNode')
            ->get()
            ->filter(fn (Workflow $workflow) => $this->isDue($workflow));

        foreach ($workflows as $workflow) {
            $engine->start($workflow);
            $workflow->update(['last_cron_run_at' => now()]);
        }

        $this->info("Started {$workflows->count()} workflow instance(s).");

        return self::SUCCESS;
    }

    private function isDue(Workflow $workflow): bool
    {
        $node = $workflow->currentVersion?->startNode;

        if (! $node || $node->type !== WorkflowNodeType::Start) {
            return false;
        }

        $config = $node->config ?? [];

        if (($config['trigger_type'] ?? null) !== WorkflowTriggerType::Cron->value) {
            return false;
        }

        $expression = $config['cron_expression'] ?? null;

        if (! $expression) {
            return false;
        }

        try {
            $cron = new CronExpression($expression);
        } catch (Throwable) {
            return false;
        }

        if (! $cron->isDue()) {
            return false;
        }

        return $workflow->last_cron_run_at === null || $workflow->last_cron_run_at->lt(now()->startOfMinute());
    }
}
