<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable API endpoint for the "Assegna variabile da API" workflow
 * action — global (workflow_id null) or scoped to one workflow. `config`
 * holds the auth shape: auth_type (none|bearer|basic|header) plus
 * token/username/password/header_name/header_value as relevant.
 */
#[Fillable(['workflow_id', 'name', 'base_url', 'config'])]
class WorkflowApiEndpoint extends Model
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
