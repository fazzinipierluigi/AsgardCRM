<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persists the opaque incremental-sync cursor (e.g. Microsoft Graph's
 * delta link) a Connector needs to pull only what changed since the
 * last run, per connector+mailbox. Connectors with no such concept
 * (e.g. EWS in this app, see EwsExchangeConnector) simply never write
 * delta_link and always do a full range pull.
 */
#[Fillable(['connector_id', 'connector_user_mailbox_id', 'delta_link', 'last_synced_at'])]
class ConnectorSyncState extends Model
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

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(ConnectorUserMailbox::class, 'connector_user_mailbox_id');
    }
}
