<?php

namespace Fazzinipierluigi\CrmCore\Enums;

enum WorkflowNodeType: string
{
    case Start = 'start';
    case UserTask = 'user_task';
    case ServiceTask = 'service_task';
    case ExclusiveGateway = 'exclusive_gateway';
    case ParallelGateway = 'parallel_gateway';
    case Timer = 'timer';
    case BoundaryTimer = 'boundary_timer';
    case Semaphore = 'semaphore';
    case End = 'end';
    case Subworkflow = 'subworkflow';

    public function label(): string
    {
        return match ($this) {
            self::Start => 'Nodo di avvio',
            self::UserTask => 'Task utente',
            self::ServiceTask => 'Task processo/script',
            self::ExclusiveGateway => 'Gate esclusivo',
            self::ParallelGateway => 'Gate parallelo',
            self::Timer => 'Timer',
            self::BoundaryTimer => 'Boundary Timer',
            self::Semaphore => 'Semaforo',
            self::End => 'Nodo di fine',
            self::Subworkflow => 'Subworkflow',
        };
    }

    /**
     * Whether a workflow may contain more than one node of this type.
     * Only the start node is capped at one per workflow.
     */
    public function isUnique(): bool
    {
        return $this === self::Start;
    }

    /**
     * Whether this node type can carry more than one outgoing edge that
     * the engine must choose between/split across, rather than a single
     * unconditional "next" edge.
     */
    public function isGateway(): bool
    {
        return $this === self::ExclusiveGateway || $this === self::ParallelGateway;
    }

    /**
     * Whether the engine blocks token advancement at this node until an
     * external event (user input, timer, joining branches) occurs.
     */
    public function isBlocking(): bool
    {
        return in_array($this, [self::UserTask, self::Timer, self::Semaphore], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
