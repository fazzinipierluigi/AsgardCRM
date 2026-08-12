<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Fazzinipierluigi\CrmCore\Enums\ImporterRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['importer_id', 'started_at', 'finished_at', 'status', 'rows_imported', 'rows_failed', 'error_message'])]
class ImporterRun extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'status' => ImporterRunStatus::class,
        ];
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(Importer::class);
    }
}
