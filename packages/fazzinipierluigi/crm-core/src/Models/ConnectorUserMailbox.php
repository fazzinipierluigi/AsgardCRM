<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps a local user to the mailbox address a Connector should sync
 * their calendar with (e.g. their Exchange/Outlook UPN) — needed
 * because a single admin-configured Connector (one app registration or
 * service account) can sync many users' mailboxes.
 */
#[Fillable(['connector_id', 'user_id', 'mailbox_email'])]
class ConnectorUserMailbox extends Model
{
    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
