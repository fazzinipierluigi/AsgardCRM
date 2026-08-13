<?php

namespace Fazzinipierluigi\AsgardCRM\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links one calendario record to its counterpart in an external
 * connector (see CalendarSyncService), so future syncs can tell "this
 * external event is already the local one with id X" apart from "this
 * is new, create a local record for it". entity_record_id points at
 * entity_calendario's dynamic table with no DB-level FK — see the
 * migration for why.
 */
#[Fillable(['entity_record_id', 'connector_id', 'user_id', 'external_id', 'external_change_key', 'sync_hash', 'last_synced_at'])]
class CalendarEventExternalLink extends Model
{
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('crm.user_model'));
    }
}
