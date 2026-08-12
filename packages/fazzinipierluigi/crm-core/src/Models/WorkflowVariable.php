<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Fazzinipierluigi\CrmCore\Enums\WorkflowVariableType;
use Fazzinipierluigi\CrmCore\Database\Factories\WorkflowVariableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workflow_version_id', 'name', 'type', 'default_value', 'is_system'])]
class WorkflowVariable extends Model
{
    /** @use HasFactory<WorkflowVariableFactory> */
    use HasFactory;

    protected static function newFactory()
    {
        return WorkflowVariableFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => WorkflowVariableType::class,
            'is_system' => 'boolean',
        ];
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }
}
