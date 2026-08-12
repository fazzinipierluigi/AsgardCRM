<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Fazzinipierluigi\CrmCore\Enums\WorkflowInstanceStatus;
use Fazzinipierluigi\CrmCore\Enums\WorkflowTokenStatus;
use Database\Factories\WorkflowInstanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workflow_id', 'workflow_version_id', 'parent_token_id', 'status', 'entity_type', 'entity_id', 'entity_slug', 'variables', 'started_at', 'ended_at', 'error_message'])]
class WorkflowInstance extends Model
{
    /** @use HasFactory<WorkflowInstanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => WorkflowInstanceStatus::class,
            'variables' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function parentToken(): BelongsTo
    {
        return $this->belongsTo(WorkflowToken::class, 'parent_token_id');
    }

    /**
     * Resolves the entity record this instance is bound to (set by a
     * start node triggered on entity created/updated, or by a
     * Subworkflow's parent). Not a normal MorphTo: EntityRecord is one
     * generic model shared by every dynamic Entity's table, with no
     * default $table and ids that only make sense within one entity —
     * so a plain entity_type/entity_id pair can't resolve it alone,
     * entity_slug says which Entity's table entity_id is a row of.
     */
    public function resolveEntity(): ?Model
    {
        if (! $this->entity_type || $this->entity_id === null) {
            return null;
        }

        if ($this->entity_type === EntityRecord::class) {
            if (! $this->entity_slug) {
                return null;
            }

            $entity = Entity::where('slug', $this->entity_slug)->first();

            return $entity ? EntityRecord::forEntity($entity)->find($this->entity_id) : null;
        }

        return $this->entity_type::find($this->entity_id);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(WorkflowToken::class);
    }

    public function activeTokens(): HasMany
    {
        return $this->tokens()->where('status', WorkflowTokenStatus::Active->value);
    }

    public function userTasks(): HasMany
    {
        return $this->hasMany(WorkflowUserTask::class);
    }

    public function timers(): HasMany
    {
        return $this->hasMany(WorkflowTimer::class);
    }

    public function getVariable(string $name): mixed
    {
        return data_get($this->variables, $name);
    }

    public function setVariable(string $name, mixed $value): void
    {
        $variables = $this->variables ?? [];
        data_set($variables, $name, $value);
        $this->variables = $variables;
    }
}
