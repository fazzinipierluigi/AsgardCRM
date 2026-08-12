<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'is_active', 'created_by', 'last_cron_run_at', 'current_version_id'])]
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_cron_run_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('crm.user_model'), 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function sqlConnections(): HasMany
    {
        return $this->hasMany(WorkflowSqlConnection::class);
    }

    public function apiEndpoints(): HasMany
    {
        return $this->hasMany(WorkflowApiEndpoint::class);
    }

    /**
     * Whether an entity record has already started an instance of this
     * workflow (any version) — backs a start node's "avvia una sola
     * volta" occurrence setting.
     */
    public function hasAlreadyTriggeredFor(string $entitySlug, int|string $entityId): bool
    {
        return $this->instances()
            ->where('entity_slug', $entitySlug)
            ->where('entity_id', $entityId)
            ->exists();
    }
}
