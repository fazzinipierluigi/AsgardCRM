<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Workflows;

use Carbon\Carbon;
use Fazzinipierluigi\AsgardCRM\Contracts\CrmUser;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionPhase;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowInstanceStatus;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowNodeType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowTimerStatus;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowTimerUnit;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowTokenStatus;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowUserTaskStatus;
use Fazzinipierluigi\AsgardCRM\Models\Workflow;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowEdge;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowInstance;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowNode;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowTimer;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowToken;
use Fazzinipierluigi\AsgardCRM\Models\WorkflowUserTask;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\TaskExecutors\QueuedTaskExecutor;
use Fazzinipierluigi\AsgardCRM\Services\Workflows\TaskExecutors\SyncTaskExecutor;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

/**
 * Drives a workflow instance forward: a token sits on exactly one node
 * at a time (or splits into several at a Gate parallelo), and the
 * engine keeps advancing every token synchronously until each either
 * finishes at a Nodo di fine or parks on a node that needs an external
 * event to continue (Task utente, Timer, Semaforo, Subworkflow in
 * attesa, or a Task processo/script running through a queue). Those
 * parked tokens are resumed later by completeUserTask(), fireTimer(),
 * ExecuteServiceTaskJob, or a child instance completing.
 *
 * Every instance is pinned to the Workflow's *current* WorkflowVersion
 * at the moment it starts (see WorkflowGraphPersister) — editing the
 * workflow later publishes a new version but never touches the nodes/
 * edges/actions an already-running instance is executing.
 */
class WorkflowEngine
{
    public function __construct(
        private readonly WorkflowActionExecutor $actions,
        private readonly WorkflowExpressionEvaluator $evaluator,
        private readonly WorkflowConditionEvaluator $conditions,
        private readonly WorkflowTokenTransitioner $transitioner,
        private readonly SyncTaskExecutor $syncTaskExecutor,
        private readonly QueuedTaskExecutor $queuedTaskExecutor,
        private readonly WorkflowNodeExecutionLogger $executionLog,
    ) {}

    /**
     * @param  array<string, mixed>  $initialVariables
     * @param  ?Model  $entity  The record that triggered this instance (an
     *                          EntityRecord for an entity created/updated
     *                          trigger). $entitySlug is required alongside
     *                          it when $entity is an EntityRecord, since
     *                          that generic model can't otherwise be
     *                          re-resolved later — see
     *                          WorkflowInstance::resolveEntity().
     * @return ?WorkflowInstance Null when the start node's
     *                           start_condition (a JsonLogic rule)
     *                           evaluates to false — no instance is
     *                           created in that case.
     */
    public function start(Workflow $workflow, array $initialVariables = [], ?Model $entity = null, ?WorkflowToken $parentToken = null, ?string $entitySlug = null): ?WorkflowInstance
    {
        $version = $workflow->currentVersion;

        if (! $version) {
            throw new RuntimeException("Il workflow «{$workflow->name}» non ha ancora una versione pubblicata.");
        }

        $startNode = $version->nodes()->where('type', WorkflowNodeType::Start->value)->first();

        if (! $startNode) {
            throw new RuntimeException("Il workflow «{$workflow->name}» non ha un nodo di avvio.");
        }

        $variables = $version->variables->mapWithKeys(
            fn ($variable) => [$variable->name => $variable->type->cast($variable->default_value)]
        )->all();
        $variables = array_merge($variables, $initialVariables);

        $startCondition = $startNode->config['start_condition'] ?? null;

        if ($startCondition) {
            $context = $variables;
            if ($entity) {
                $context['entity'] = $entity;
            }

            if (! $this->conditions->evaluate($startCondition, $context)) {
                return null;
            }
        }

        $instance = WorkflowInstance::create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $version->id,
            'parent_token_id' => $parentToken?->id,
            'status' => WorkflowInstanceStatus::Running,
            'entity_type' => $entity?->getMorphClass(),
            'entity_id' => $entity?->getKey(),
            'entity_slug' => $entitySlug,
            'variables' => $variables,
            'started_at' => now(),
        ]);

        $instance->tokens()->create([
            'workflow_node_id' => $startNode->id,
            'status' => WorkflowTokenStatus::Active,
        ]);

        $this->advance($instance);

        return $instance->fresh();
    }

    /**
     * Processes every currently-active token of the instance until none
     * remain (each has either finished or parked waiting on an event).
     */
    public function advance(WorkflowInstance $instance): void
    {
        while (true) {
            $token = $instance->activeTokens()->with('node')->first();

            if (! $token) {
                break;
            }

            try {
                $this->processToken($instance, $token);
            } catch (Throwable $e) {
                $this->executionLog->fail($instance, $token);

                $instance->status = WorkflowInstanceStatus::Failed;
                $instance->error_message = $e->getMessage();
                $instance->ended_at = now();
                $instance->save();

                return;
            }
        }

        $this->checkCompletion($instance);
    }

    public function completeUserTask(WorkflowUserTask $task, array $formData, ?CrmUser $completedBy = null): void
    {
        $task->form_data = $formData;
        $task->status = WorkflowUserTaskStatus::Completed;
        $task->completed_by = $completedBy?->id;
        $task->completed_at = now();
        $task->save();

        $instance = $task->instance;
        $node = $task->node;

        foreach ((array) ($node->config['form_fields'] ?? []) as $field) {
            if (empty($field['bind_variable'])) {
                continue;
            }

            $instance->setVariable($field['bind_variable'], $formData[$field['name']] ?? null);
        }
        $instance->save();

        $this->runActions($instance, $node, WorkflowActionPhase::After);

        $token = $task->token;
        $edge = $node->outgoingEdges()->first();
        $this->traverse($instance, $token, $edge);

        $this->advance($instance);
    }

    public function fireTimer(WorkflowTimer $timer): void
    {
        if ($timer->node->type === WorkflowNodeType::BoundaryTimer) {
            $this->fireBoundaryTimer($timer);

            return;
        }

        $timer->status = WorkflowTimerStatus::Fired;
        $timer->save();

        $instance = $timer->instance;
        $node = $timer->node;
        $token = $timer->token;

        $this->runActions($instance, $node, WorkflowActionPhase::After);

        $edge = $node->outgoingEdges()->first();
        $this->traverse($instance, $token, $edge);

        $this->advance($instance);
    }

    /**
     * The timeout branch of a Boundary Timer attached to a Task
     * utente/Task processo/script asincrono: fires the node's outgoing
     * edge instead of the host's, taking over the token that was
     * parked waiting on the host.
     */
    private function fireBoundaryTimer(WorkflowTimer $timer): void
    {
        // The host may have completed through its normal path since
        // FireDueWorkflowTimers queried this row, cancelling it (see
        // WorkflowTokenTransitioner::traverse()) — a fresh read confirms
        // there's still something to do before this fires it for good.
        if ($timer->fresh()->status !== WorkflowTimerStatus::Pending) {
            return;
        }

        $timer->status = WorkflowTimerStatus::Fired;
        $timer->save();

        $instance = $timer->instance;
        $node = $timer->node;
        $token = $timer->token;

        WorkflowUserTask::where('workflow_token_id', $token->id)
            ->where('status', WorkflowUserTaskStatus::Pending->value)
            ->update(['status' => WorkflowUserTaskStatus::Expired->value]);

        $this->runActions($instance, $node, WorkflowActionPhase::After);

        $edge = $node->outgoingEdges()->first();

        if (! $edge) {
            throw new RuntimeException("Il Boundary Timer «{$node->name}» non ha un arco in uscita.");
        }

        $this->traverse($instance, $token, $edge);

        $this->advance($instance);
    }

    private function processToken(WorkflowInstance $instance, WorkflowToken $token): void
    {
        $node = $token->node;

        $this->executionLog->enter($instance, $token);

        match ($node->type) {
            WorkflowNodeType::Start => $this->handleThroughNode($instance, $token, $node),
            WorkflowNodeType::ServiceTask => $this->handleServiceTask($instance, $token, $node),
            WorkflowNodeType::UserTask => $this->handleUserTask($instance, $token, $node),
            WorkflowNodeType::ExclusiveGateway => $this->handleExclusiveGateway($instance, $token, $node),
            WorkflowNodeType::ParallelGateway => $this->handleParallelGateway($instance, $token, $node),
            WorkflowNodeType::Timer => $this->handleTimer($instance, $token, $node),
            WorkflowNodeType::Semaphore => $this->handleSemaphore($instance, $token, $node),
            WorkflowNodeType::End => $this->handleEnd($instance, $token, $node),
            WorkflowNodeType::Subworkflow => $this->handleSubworkflow($instance, $token, $node),
        };
    }

    /**
     * Start and Task processo/script: run the node's actions and move
     * straight to its single outgoing edge.
     */
    private function handleThroughNode(WorkflowInstance $instance, WorkflowToken $token, WorkflowNode $node): void
    {
        $this->runActions($instance, $node, WorkflowActionPhase::Before);
        $this->runActions($instance, $node, WorkflowActionPhase::After);

        $edge = $node->outgoingEdges()->first();

        if (! $edge) {
            throw new RuntimeException("Il nodo «{$node->name}» non ha un arco in uscita.");
        }

        $this->traverse($instance, $token, $edge);
    }

    /**
     * Unlike every other node type, a Task processo/script's activity may
     * be configured (`config.execution_mode === 'async'`) to run through
     * a queue instead of in-process — the engine itself never executes
     * it either way, it only picks which TaskExecutor does.
     */
    private function handleServiceTask(WorkflowInstance $instance, WorkflowToken $token, WorkflowNode $node): void
    {
        $isAsync = ($node->config['execution_mode'] ?? 'sync') === 'async';
        $executor = $isAsync ? $this->queuedTaskExecutor : $this->syncTaskExecutor;

        $executor->execute($node, $instance, $token);

        // Only an async activity actually parks the token waiting on an
        // external event (the queued job) long enough for a Boundary
        // Timer to make sense — a sync one already ran to completion
        // and traversed away inside execute() above.
        if ($isAsync) {
            $this->attachBoundaryTimerIfAny($instance, $token, $node);
        }
    }

    private function handleUserTask(WorkflowInstance $instance, WorkflowToken $token, WorkflowNode $node): void
    {
        $this->runActions($instance, $node, WorkflowActionPhase::Before);

        $config = $node->config ?? [];

        WorkflowUserTask::create([
            'workflow_instance_id' => $instance->id,
            'workflow_node_id' => $node->id,
            'workflow_token_id' => $token->id,
            ...$this->resolveAssignee($instance, $config),
            'status' => WorkflowUserTaskStatus::Pending,
        ]);

        $token->status = WorkflowTokenStatus::WaitingUserTask;
        $token->save();

        $this->attachBoundaryTimerIfAny($instance, $token, $node);
    }

    /**
     * If a Boundary Timer node is anchored to $node (see
     * WorkflowGraphPersister for how config.attached_to_node_id gets
     * resolved), park a WorkflowTimer for it on the same token — kept
     * as its own row rather than changing the token's own status, so
     * the host's own WaitingUserTask/WaitingActivity status is
     * untouched: whichever of the two happens first (the host
     * completing normally, or this timer firing) is a race the token's
     * single status can't represent by itself.
     */
    private function attachBoundaryTimerIfAny(WorkflowInstance $instance, WorkflowToken $token, WorkflowNode $node): void
    {
        $boundary = WorkflowNode::where('workflow_version_id', $node->workflow_version_id)
            ->where('type', WorkflowNodeType::BoundaryTimer->value)
            ->where('config->attached_to_node_id', $node->id)
            ->first();

        if (! $boundary) {
            return;
        }

        $context = $this->actions->buildContext($instance);
        $runAt = $this->resolveTimerRunAt($boundary->config ?? [], $context);

        WorkflowTimer::create([
            'workflow_instance_id' => $instance->id,
            'workflow_node_id' => $boundary->id,
            'workflow_token_id' => $token->id,
            'run_at' => $runAt,
            'status' => WorkflowTimerStatus::Pending,
        ]);
    }

    /**
     * A Task utente's assignee, per its `config.assignment_mode`:
     * - 'role' (also the default when the key is missing, so nodes saved
     *   before this mode existed keep behaving exactly as before): the
     *   fixed `assigned_role_id`.
     * - 'user': the fixed `assigned_user_id`.
     * - 'expression': `assignee_expression` evaluated against the same
     *   context (variables + triggering entity) as any other expression
     *   in the engine — e.g. `entity.responsabile_id`. A result that
     *   isn't an existing user's id leaves the task unassigned (open
     *   queue, same as a node with no assignee configured at all) rather
     *   than failing the instance; a broken expression itself still
     *   throws and fails the instance, like every other expression.
     *
     * @param  array<string, mixed>  $config
     * @return array{assigned_role_id: ?int, assigned_user_id: ?int}
     */
    private function resolveAssignee(WorkflowInstance $instance, array $config): array
    {
        $mode = $config['assignment_mode'] ?? 'role';

        if ($mode === 'user') {
            return ['assigned_role_id' => null, 'assigned_user_id' => $config['assigned_user_id'] ?? null];
        }

        if ($mode === 'expression') {
            $value = $this->evaluator->evaluate($config['assignee_expression'] ?? null, $this->actions->buildContext($instance));
            $userId = is_numeric($value) ? (int) $value : null;

            return [
                'assigned_role_id' => null,
                'assigned_user_id' => $userId && config('crm.user_model')::whereKey($userId)->exists() ? $userId : null,
            ];
        }

        return ['assigned_role_id' => $config['assigned_role_id'] ?? null, 'assigned_user_id' => null];
    }

    private function handleExclusiveGateway(WorkflowInstance $instance, WorkflowToken $token, WorkflowNode $node): void
    {
        $this->runActions($instance, $node, WorkflowActionPhase::Before);

        $context = $this->actions->buildContext($instance);

        foreach ($node->outgoingEdges as $edge) {
            if ($this->conditions->evaluate($edge->condition_logic, $context)) {
                $this->traverse($instance, $token, $edge);

                return;
            }
        }

        throw new RuntimeException("Nessuna condizione del gate esclusivo «{$node->name}» è risultata vera.");
    }

    private function handleParallelGateway(WorkflowInstance $instance, WorkflowToken $token, WorkflowNode $node): void
    {
        $this->runActions($instance, $node, WorkflowActionPhase::Before);
        $this->runActions($instance, $node, WorkflowActionPhase::After);

        $edges = $node->outgoingEdges;

        if ($edges->isEmpty()) {
            throw new RuntimeException("Il gate parallelo «{$node->name}» non ha archi in uscita.");
        }

        foreach ($edges as $index => $edge) {
            if ($index === 0) {
                $this->traverse($instance, $token, $edge);

                continue;
            }

            $branch = $instance->tokens()->create([
                'workflow_node_id' => $edge->source_node_id,
                'status' => WorkflowTokenStatus::Active,
            ]);

            $this->traverse($instance, $branch, $edge);
        }
    }

    private function handleTimer(WorkflowInstance $instance, WorkflowToken $token, WorkflowNode $node): void
    {
        $context = $this->actions->buildContext($instance);
        $runAt = $this->resolveTimerRunAt($node->config ?? [], $context);

        WorkflowTimer::create([
            'workflow_instance_id' => $instance->id,
            'workflow_node_id' => $node->id,
            'workflow_token_id' => $token->id,
            'run_at' => $runAt,
            'status' => WorkflowTimerStatus::Pending,
        ]);

        $token->status = WorkflowTokenStatus::WaitingTimer;
        $token->save();
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $context
     */
    private function resolveTimerRunAt(array $config, array $context): Carbon
    {
        $base = ($config['reference'] ?? 'fixed') === 'variable'
            ? Carbon::parse($context[$config['variable_name'] ?? ''] ?? 'now')
            : Carbon::parse($config['date'] ?? 'now');

        $unit = WorkflowTimerUnit::tryFrom($config['unit'] ?? '') ?? WorkflowTimerUnit::Minutes;
        $amount = (int) ($config['amount'] ?? 0);
        $direction = $config['direction'] ?? 'after';

        return match ($unit) {
            WorkflowTimerUnit::Minutes => $direction === 'before' ? $base->subMinutes($amount) : $base->addMinutes($amount),
            WorkflowTimerUnit::Hours => $direction === 'before' ? $base->subHours($amount) : $base->addHours($amount),
            WorkflowTimerUnit::Days => $direction === 'before' ? $base->subDays($amount) : $base->addDays($amount),
        };
    }

    private function handleSemaphore(WorkflowInstance $instance, WorkflowToken $token, WorkflowNode $node): void
    {
        $token->status = WorkflowTokenStatus::WaitingJoin;
        $token->save();

        $required = $node->incomingEdges()->count();
        $waiting = $instance->tokens()
            ->where('workflow_node_id', $node->id)
            ->where('status', WorkflowTokenStatus::WaitingJoin->value)
            ->get();

        if ($waiting->count() < max($required, 1)) {
            return;
        }

        $survivor = $waiting->first();
        $waiting->skip(1)->each(function (WorkflowToken $t) use ($instance) {
            $t->update(['status' => WorkflowTokenStatus::Cancelled]);
            $this->executionLog->exit($instance, $t);
        });

        $this->runActions($instance, $node, WorkflowActionPhase::Before);
        $this->runActions($instance, $node, WorkflowActionPhase::After);

        $edge = $node->outgoingEdges()->first();

        if (! $edge) {
            throw new RuntimeException("Il semaforo «{$node->name}» non ha un arco in uscita.");
        }

        $survivor->status = WorkflowTokenStatus::Active;
        $this->traverse($instance, $survivor, $edge);
    }

    private function handleEnd(WorkflowInstance $instance, WorkflowToken $token, WorkflowNode $node): void
    {
        $this->runActions($instance, $node, WorkflowActionPhase::Before);

        $token->status = WorkflowTokenStatus::Completed;
        $token->save();

        $this->executionLog->exit($instance, $token);
    }

    private function handleSubworkflow(WorkflowInstance $instance, WorkflowToken $token, WorkflowNode $node): void
    {
        $this->runActions($instance, $node, WorkflowActionPhase::Before);

        $config = $node->config ?? [];
        $child = Workflow::findOrFail($config['workflow_id']);

        $context = $this->actions->buildContext($instance);
        $childVariables = [];
        foreach ((array) ($config['input_mapping'] ?? []) as $mapping) {
            $childVariables[$mapping['variable']] = $this->evaluator->evaluate($mapping['expression'], $context);
        }

        $waits = (bool) ($config['wait_for_completion'] ?? true);

        if ($waits) {
            $token->status = WorkflowTokenStatus::WaitingSubworkflow;
            $token->save();

            $childInstance = $this->start($child, $childVariables, null, $token);

            // The child's own start_condition blocked it — nothing will
            // ever resume this token, so treat the subworkflow step as a
            // no-op and continue right away instead of leaving it parked.
            if ($childInstance === null) {
                $this->runActions($instance, $node, WorkflowActionPhase::After);

                $edge = $node->outgoingEdges()->first();

                if (! $edge) {
                    throw new RuntimeException("Il subworkflow «{$node->name}» non ha un arco in uscita.");
                }

                $token->status = WorkflowTokenStatus::Active;
                $this->traverse($instance, $token, $edge);
            }

            return;
        }

        $this->start($child, $childVariables);

        $this->runActions($instance, $node, WorkflowActionPhase::After);

        $edge = $node->outgoingEdges()->first();

        if (! $edge) {
            throw new RuntimeException("Il subworkflow «{$node->name}» non ha un arco in uscita.");
        }

        $this->traverse($instance, $token, $edge);
    }

    /**
     * Moves a token across an edge: runs the edge's own before/after
     * actions, then repositions the token on the edge's target node
     * (still Active, so the next advance() loop turn processes it).
     */
    private function traverse(WorkflowInstance $instance, WorkflowToken $token, WorkflowEdge $edge): void
    {
        $this->transitioner->traverse($instance, $token, $edge);
    }

    private function runActions(WorkflowInstance $instance, Model $actionable, WorkflowActionPhase $phase): void
    {
        $this->transitioner->runActions($instance, $actionable, $phase);
    }

    /**
     * An instance is done once every token it ever created has either
     * finished or been cancelled (a Semaforo discards the branches that
     * lose the join) — at that point, if this instance was started by a
     * waiting Subworkflow node, that parent token is resumed.
     */
    private function checkCompletion(WorkflowInstance $instance): void
    {
        if ($instance->status !== WorkflowInstanceStatus::Running) {
            return;
        }

        $unfinished = $instance->tokens()
            ->whereNotIn('status', [WorkflowTokenStatus::Completed->value, WorkflowTokenStatus::Cancelled->value])
            ->exists();

        if ($unfinished) {
            return;
        }

        $instance->status = WorkflowInstanceStatus::Completed;
        $instance->ended_at = now();
        $instance->save();

        $parentToken = $instance->parentToken;

        if (! $parentToken) {
            return;
        }

        $parentInstance = $parentToken->instance;
        $node = $parentToken->node;

        $this->runActions($parentInstance, $node, WorkflowActionPhase::After);

        $edge = $node->outgoingEdges()->first();

        if (! $edge) {
            throw new RuntimeException("Il subworkflow «{$node->name}» non ha un arco in uscita.");
        }

        $this->traverse($parentInstance, $parentToken, $edge);

        $this->advance($parentInstance);
    }
}
