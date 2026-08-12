<?php

namespace Fazzinipierluigi\CrmCore\Models;

use Fazzinipierluigi\CrmCore\Contracts\CrmUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admin-authored signature template (`/admin/mail-signatures`) — free
 * HTML written in HugeRTE, with literal `{{user.*}}` placeholders a
 * MailAccount owner never edits directly. Assigned to a MailAccount
 * (nullable `mail_signature_id`, see that model) by the mailbox's own
 * owner from "Le mie caselle"; render() is what turns the template
 * into the HTML actually shown in the compose editor and sent —
 * always resolved against the *current* user, never the admin who
 * authored the template.
 */
#[Fillable(['name', 'body_html'])]
class MailSignature extends Model
{
    private const PLACEHOLDERS = ['name', 'email', 'phone', 'job_title'];

    public function mailAccounts(): HasMany
    {
        return $this->hasMany(MailAccount::class);
    }

    /**
     * Replaces every recognized `{{user.*}}` token with the given
     * user's own field, HTML-escaped (a user's name/phone/job title is
     * free text they or an admin entered — never trusted as HTML).
     * Unrecognized tokens (a typo, or a placeholder this version
     * doesn't support) are left as-is rather than silently dropped, so
     * a broken template stays visibly broken instead of quietly losing
     * text.
     */
    public function render(CrmUser $user): string
    {
        $replacements = [];

        foreach (self::PLACEHOLDERS as $field) {
            $replacements['{{user.'.$field.'}}'] = e((string) ($user->{$field} ?? ''));
        }

        return strtr($this->body_html, $replacements);
    }
}
