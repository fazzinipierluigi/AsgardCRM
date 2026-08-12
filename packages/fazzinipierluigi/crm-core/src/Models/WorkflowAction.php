<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Fazzinipierluigi\CrmCore\Enums\WorkflowActionPhase;
use Fazzinipierluigi\CrmCore\Enums\WorkflowActionType;
use Fazzinipierluigi\CrmCore\Database\Factories\WorkflowActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['workflow_version_id', 'actionable_type', 'actionable_id', 'phase', 'sequence', 'type', 'config'])]
class WorkflowAction extends Model
{
    /** @use HasFactory<WorkflowActionFactory> */
    use HasFactory;

    protected static function newFactory()
    {
        return WorkflowActionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'phase' => WorkflowActionPhase::class,
            'type' => WorkflowActionType::class,
            'config' => 'array',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function actionable(): MorphTo
    {
        return $this->morphTo();
    }
}
