<?php

namespace Fazzinipierluigi\AsgardCRM\Models;

use Fazzinipierluigi\AsgardCRM\Database\Factories\WorkflowNodeFactory;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowActionPhase;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowNodeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['workflow_version_id', 'type', 'name', 'pos_x', 'pos_y', 'config'])]
class WorkflowNode extends Model
{
    /** @use HasFactory<WorkflowNodeFactory> */
    use HasFactory;

    protected static function newFactory()
    {
        return WorkflowNodeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => WorkflowNodeType::class,
            'config' => 'array',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class, 'source_node_id')->orderBy('sequence');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class, 'target_node_id');
    }

    public function actions(): MorphMany
    {
        return $this->morphMany(WorkflowAction::class, 'actionable')->orderBy('sequence');
    }

    public function actionsFor(WorkflowActionPhase $phase): MorphMany
    {
        return $this->actions()->where('phase', $phase->value);
    }
}
