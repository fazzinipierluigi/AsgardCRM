<?php

namespace Fazzinipierluigi\AsgardCRM\Models;

use Fazzinipierluigi\AsgardCRM\Database\Factories\WorkflowVersionFactory;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowNodeType;
use Fazzinipierluigi\AsgardCRM\Enums\WorkflowVersionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['workflow_id', 'version', 'status', 'published_at'])]
class WorkflowVersion extends Model
{
    /** @use HasFactory<WorkflowVersionFactory> */
    use HasFactory;

    protected static function newFactory()
    {
        return WorkflowVersionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => WorkflowVersionStatus::class,
            'published_at' => 'datetime',
        ];
    }

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
