<?php

namespace App\Services\Workflows;

use App\Enums\WorkflowActionPhase;
use App\Enums\WorkflowInstanceStatus;
use App\Enums\WorkflowNodeType;
use App\Enums\WorkflowTimerStatus;
use App\Enums\WorkflowTimerUnit;
use App\Enums\WorkflowTokenStatus;
use App\Enums\WorkflowUserTaskStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowEdge;
use App\Models\WorkflowInstance;
use App\Models\WorkflowNode;
use App\Models\WorkflowTimer;
use App\Models\WorkflowToken;
use App\Models\WorkflowUserTask;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

/**
 * Drives a workflow instance forward: a token sits on exactly one node
 * at a time (or splits into several at a Gate parallelo), and the
 * engine keeps advancing every token synchronously until each either
 * finishes at a Nodo di fine or parks on a node that needs an external
 * event to continue (Task utente, Timer, Semaforo, Subworkflow in
 * attesa). Those parked tokens are resumed later by completeUserTask(),
 * fireTimer(), or a child instance completing.
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
                $instance->status = WorkflowInstanceStatus::Failed;
                $instance->error_message = $e->getMessage();
                $instance->ended_at = now();
                $instance->save();

                return;
            }
        }

        $this->checkCompletion($instance);
    }

    public function completeUserTask(WorkflowUserTask $task, array $formData, ?User $completedBy = null): void
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

    private function processToken(WorkflowInstance $instance, WorkflowToken $token): void
    {
        $node = $token->node;

        match ($node->type) {
            WorkflowNodeType::Start => $this->handleThroughNode($instance, $token, $node),
            WorkflowNodeType::ServiceTask => $this->handleThroughNode($instance, $token, $node),
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
                'assigned_user_id' => $userId && User::whereKey($userId)->exists() ? $userId : null,
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
        $config = $node->config ?? [];
        $context = $this->actions->buildContext($instance);

        $base = ($config['reference'] ?? 'fixed') === 'variable'
            ? Carbon::parse($context[$config['variable_name'] ?? ''] ?? 'now')
            : Carbon::parse($config['date'] ?? 'now');

        $unit = WorkflowTimerUnit::tryFrom($config['unit'] ?? '') ?? WorkflowTimerUnit::Minutes;
        $amount = (int) ($config['amount'] ?? 0);
        $direction = $config['direction'] ?? 'after';

        $runAt = match ($unit) {
            WorkflowTimerUnit::Minutes => $direction === 'before' ? $base->subMinutes($amount) : $base->addMinutes($amount),
            WorkflowTimerUnit::Hours => $direction === 'before' ? $base->subHours($amount) : $base->addHours($amount),
            WorkflowTimerUnit::Days => $direction === 'before' ? $base->subDays($amount) : $base->addDays($amount),
        };

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
        $waiting->skip(1)->each(fn (WorkflowToken $t) => $t->update(['status' => WorkflowTokenStatus::Cancelled]));

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
        $this->runActions($instance, $edge, WorkflowActionPhase::Before);
        $this->runActions($instance, $edge, WorkflowActionPhase::After);

        $token->workflow_node_id = $edge->target_node_id;
        $token->via_edge_id = $edge->id;
        $token->status = WorkflowTokenStatus::Active;
        $token->save();
    }

    private function runActions(WorkflowInstance $instance, Model $actionable, WorkflowActionPhase $phase): void
    {
        foreach ($actionable->actionsFor($phase)->get() as $action) {
            $this->actions->execute($action, $instance);
        }
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
