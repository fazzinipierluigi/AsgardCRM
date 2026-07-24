<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable SQL connection for the "Assegna variabile da SQL" workflow
 * action — global (workflow_id null) or scoped to one workflow. `config`
 * holds driver/host/port/database/username/password, the same shape
 * App\Services\DynamicDatabaseConnector expects (see
 * Importers\Channels\DatabaseImporterChannel, the original source of this
 * config shape).
 */
#[Fillable(['workflow_id', 'name', 'config'])]
class WorkflowSqlConnection extends Model
{
    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
