<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Fazzinipierluigi\CrmCore\Enums\WorkflowUserTaskStatus;
use Fazzinipierluigi\CrmCore\Database\Factories\WorkflowUserTaskFactory;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'workflow_instance_id',
    'workflow_node_id',
    'workflow_token_id',
    'assigned_role_id',
    'assigned_user_id',
    'status',
    'form_data',
    'completed_by',
    'completed_at',
])]
class WorkflowUserTask extends Model
{
    /** @use HasFactory<WorkflowUserTaskFactory> */
    use HasFactory;

    protected static function newFactory()
    {
        return WorkflowUserTaskFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => WorkflowUserTaskStatus::class,
            'form_data' => 'array',
            'completed_at' => 'datetime',
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

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'assigned_role_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
