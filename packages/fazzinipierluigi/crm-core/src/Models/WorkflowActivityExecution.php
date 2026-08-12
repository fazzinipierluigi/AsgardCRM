<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Fazzinipierluigi\CrmCore\Enums\WorkflowActivityExecutionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workflow_token_id', 'status'])]
class WorkflowActivityExecution extends Model
{
    protected function casts(): array
    {
        return [
            'status' => WorkflowActivityExecutionStatus::class,
        ];
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(WorkflowToken::class, 'workflow_token_id');
    }
}
