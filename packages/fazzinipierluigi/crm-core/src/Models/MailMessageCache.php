<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One cached message header (folder page 1 only) — see
 * Fazzinipierluigi\CrmCore\Services\Mail\MailMessageHeaderCache, the only place these rows
 * are ever written or read. Never holds a body: this exists purely so
 * the webmail UI can paint a folder's message list instantly from the
 * database instead of waiting on a live IMAP/EWS/Graph round trip
 * every time, then quietly refreshes itself in the background — see
 * MailController::messages().
 */
#[Fillable(['mail_account_id', 'folder', 'uid', 'subject', 'from_address', 'from_name', 'message_date', 'has_attachments', 'is_read'])]
class MailMessageCache extends Model
{
    protected function casts(): array
    {
        return [
            'message_date' => 'datetime',
            'has_attachments' => 'boolean',
            'is_read' => 'boolean',
        ];
    }
}
