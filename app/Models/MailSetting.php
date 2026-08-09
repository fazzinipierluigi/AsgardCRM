<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row (always id 1) holding global policy for the "E-mail"
 * module — connection timeout, max attachment size a user is allowed
 * to download, which protocols are offered when adding a mailbox, the
 * short-lived, ephemeral cache window paginated (page 2+) message
 * listings are held in (see App\Http\Controllers\MailController::
 * messages() — a folder's first page is a separate, persisted cache
 * instead, see App\Services\Mail\MailMessageHeaderCache, not governed
 * by this TTL at all), and — the one secret this model does hold —
 * each OAuth provider's app registration (client id/secret), shared by
 * every user's "Connetti con Google/Microsoft" button rather than
 * asking each of them to register their own app (see
 * App\Services\Mail\OAuth\MailOAuthService).
 */
#[Fillable(['connection_timeout_seconds', 'max_attachment_size_kb', 'enabled_protocols', 'cache_ttl_seconds', 'google_oauth_client_id', 'google_oauth_client_secret', 'microsoft_oauth_client_id', 'microsoft_oauth_client_secret'])]
class MailSetting extends Model
{
    protected function casts(): array
    {
        return [
            'enabled_protocols' => 'array',
            'connection_timeout_seconds' => 'integer',
            'max_attachment_size_kb' => 'integer',
            'cache_ttl_seconds' => 'integer',
            'google_oauth_client_secret' => 'encrypted',
            'microsoft_oauth_client_secret' => 'encrypted',
        ];
    }

    /**
     * Every column is set explicitly here, even the ones with a DB-level
     * default (see the create_mail_settings_table migration) — after
     * Eloquent's create(), the in-memory model only holds what was
     * passed in, not what the database applied for omitted columns, so
     * relying on the schema default would leave this instance's
     * properties null until the next fetch.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'connection_timeout_seconds' => 10,
            'max_attachment_size_kb' => 25600,
            'enabled_protocols' => ['imap', 'pop3', 'exchange'],
            'cache_ttl_seconds' => 60,
        ]);
    }
}
