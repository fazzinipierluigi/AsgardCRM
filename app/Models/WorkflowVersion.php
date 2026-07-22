<?php

namespace App\Models;

use App\Enums\WorkflowNodeType;
use Database\Factories\WorkflowVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['workflow_id', 'version'])]
class WorkflowVersion extends Model
{
    /** @use HasFactory<WorkflowVersionFactory> */
    use HasFactory;

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function variables(): HasMany
    {
        return $this->hasMany(WorkflowVariable::class);
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(WorkflowNode::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function startNode(): HasOne
    {
        return $this->hasOne(WorkflowNode::class)->where('type', WorkflowNodeType::Start->value);
    }
}
