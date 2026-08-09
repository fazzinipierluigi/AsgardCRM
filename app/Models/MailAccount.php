<?php

namespace App\Models;

use App\Enums\MailAccountProtocol;
use App\Enums\MailAuthMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's personal mailbox (IMAP/POP3/Exchange), added self-service
 * from their own "Le mie caselle" screen (App\Http\Controllers\
 * MailAccountController) — not admin-managed, unlike MailConnector.
 * `config` holds direct protocol credentials — host/port/encryption/
 * username/password plus smtp_* for sending when auth_method is
 * Password, or oauth_provider/access_token/refresh_token/
 * token_expires_at when it's one of MailAuthMethod's OAuth cases (see
 * App\Services\Mail\OAuth\MailOAuthService) — and is empty when
 * mail_connector_id is set instead — see App\Services\Mail\
 * MailClientFactory for how these are resolved into a live client.
 */
#[Fillable(['user_id', 'protocol', 'auth_method', 'name', 'email_address', 'is_active', 'mail_connector_id', 'mail_signature_id', 'config', 'last_tested_at', 'last_test_status', 'last_test_message'])]
class MailAccount extends Model
{
    /**
     * Mirrors the `auth_method` column's own DB default — without it,
     * a MailAccount::create() call that (like most of the app's
     * existing ones, and every one predating this column) never
     * mentions auth_method at all would leave this in-memory instance
     * with a null attribute until the next fetch from the database,
     * making usesOAuth() true by accident (null !== MailAuthMethod::
     * Password) — same class of bug MailSetting::current()'s own
     * docblock explains.
     */
    protected $attributes = [
        'auth_method' => 'password',
    ];

    protected function casts(): array
    {
        return [
            'protocol' => MailAccountProtocol::class,
            'auth_method' => MailAuthMethod::class,
            'is_active' => 'boolean',
            'config' => 'encrypted:array',
            'last_tested_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function mailConnector(): BelongsTo
    {
        return $this->belongsTo(MailConnector::class);
    }

    public function mailSignature(): BelongsTo
    {
        return $this->belongsTo(MailSignature::class);
    }

    /**
     * The account's assigned signature, rendered for $user (almost
     * always this account's own owner — see MailController::compose(),
     * the only real caller) — empty string when none is assigned, so
     * callers can splice it into a compose body unconditionally.
     */
    public function renderedSignatureHtml(User $user): string
    {
        return $this->mailSignature?->render($user) ?? '';
    }

    public function usesSharedConnector(): bool
    {
        return $this->mail_connector_id !== null;
    }

    public function usesOAuth(): bool
    {
        return $this->auth_method !== MailAuthMethod::Password;
    }

    /**
     * True once the "Connetti con Google/Microsoft" flow has completed
     * at least once (MailOAuthService::handleCallback() stores a
     * refresh_token) — an account can have auth_method set to an OAuth
     * case yet still be unconnected, e.g. right after creation, before
     * the user has clicked through consent.
     */
    public function isOAuthConnected(): bool
    {
        return $this->usesOAuth() && ! empty($this->config['refresh_token'] ?? null);
    }
}
