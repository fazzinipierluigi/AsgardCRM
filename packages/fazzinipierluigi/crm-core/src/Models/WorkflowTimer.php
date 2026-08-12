<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Fazzinipierluigi\CrmCore\Enums\WorkflowTimerStatus;
use Fazzinipierluigi\CrmCore\Database\Factories\WorkflowTimerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workflow_instance_id', 'workflow_node_id', 'workflow_token_id', 'run_at', 'status'])]
class WorkflowTimer extends Model
{
    /** @use HasFactory<WorkflowTimerFactory> */
    use HasFactory;

    protected static function newFactory()
    {
        return WorkflowTimerFactory::new();
    }

    protected function casts(): array
    {
        return [
            'run_at' => 'datetime',
            'status' => WorkflowTimerStatus::class,
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
}
