<?php

namespace Fazzinipierluigi\AsgardCRM\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per (mail_account_id, folder) tracking when its
 * MailMessageCache page was last synced live and how many messages the
 * folder held at that point — see Fazzinipierluigi\AsgardCRM\Services\Mail\
 * MailMessageHeaderCache.
 */
#[Fillable(['mail_account_id', 'folder', 'total', 'synced_at'])]
class MailFolderCacheSync extends Model
{
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'synced_at' => 'datetime',
        ];
    }
}
