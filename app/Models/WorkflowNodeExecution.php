<?php

namespace App\Models;

use App\Enums\WorkflowNodeExecutionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (instance, node, iteration) visit — the log the "Flussi"
 * tab on an entity record's detail page reads from. Written by
 * WorkflowNodeExecutionLogger, called from WorkflowEngine (on entry)
 * and WorkflowTokenTransitioner (on exit), so every node type is
 * covered uniformly without each handle*() method having to know about
 * logging.
 */
class WorkflowNodeExecution extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => WorkflowNodeExecutionStatus::class,
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
            'variables_snapshot' => 'array',
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

    public function token(): BelongsTo
    {
        return $this->belongsTo(WorkflowToken::class, 'workflow_token_id');
    }

    public function viaEdge(): BelongsTo
    {
        return $this->belongsTo(WorkflowEdge::class, 'via_edge_id');
    }
}
