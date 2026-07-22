<?php

namespace App\Models;

use App\Enums\WorkflowActionPhase;
use Database\Factories\WorkflowEdgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['workflow_version_id', 'source_node_id', 'target_node_id', 'label', 'sequence', 'condition_logic'])]
class WorkflowEdge extends Model
{
    /** @use HasFactory<WorkflowEdgeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'condition_logic' => 'array',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'source_node_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'target_node_id');
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
