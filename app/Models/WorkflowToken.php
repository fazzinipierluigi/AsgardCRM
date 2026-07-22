<?php

namespace App\Models;

use App\Enums\WorkflowTokenStatus;
use Database\Factories\WorkflowTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workflow_instance_id', 'workflow_node_id', 'via_edge_id', 'status'])]
class WorkflowToken extends Model
{
    /** @use HasFactory<WorkflowTokenFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => WorkflowTokenStatus::class,
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(WorkflowNode::class, 'workflow_node_id');
    }

    public function viaEdge(): BelongsTo
    {
        return $this->belongsTo(WorkflowEdge::class, 'via_edge_id');
    }
}
