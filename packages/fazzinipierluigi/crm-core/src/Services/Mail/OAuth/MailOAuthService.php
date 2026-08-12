<?php

namespace Fazzinipierluigi\CrmCore\Services\Mail\OAuth;

use Fazzinipierluigi\CrmCore\Enums\MailOAuthProvider;
use Fazzinipierluigi\CrmCore\Models\MailAccount;
use Fazzinipierluigi\CrmCore\Models\MailSetting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Drives the delegated OAuth2 authorization-code flow a MailAccount
 * uses instead of a stored password (see MailAuthMethod) — building
 * the "Connetti con Google/Microsoft" redirect, exchanging the
 * callback's code for tokens, and keeping the access token fresh via
 * the refresh_token grant. Every call is provider-agnostic: it only
 * ever talks to MailOAuthProvider's own authorizeUrl()/tokenUrl()/
 * scope(), so a further provider needs nothing here beyond a new enum
 * case plus its client id/secret in MailSetting.
 *
 * Tokens live in the account's own encrypted `config` column
 * (oauth_provider/access_token/refresh_token/token_expires_at) — the
 * same column a password-authenticated account stores host/port/
 * credentials in, just a disjoint key set (see MailAccount's own
 * docblock and MailAccountController::configFor()).
 */
class MailOAuthService
{
    public function isConfigured(MailOAuthProvider $provider): bool
    {
        return $this->clientId($provider) !== null && $this->clientSecret($provider) !== null;
    }

    /**
     * `state` carries the account id plus a nonce stashed in the
     * session — encrypt() alone already guarantees the value can't be
     * forged (it's authenticated, not just encoded), the session nonce
     * on top of that guarantees the callback that consumes it is
     * completing a flow this same browser session actually started,
     * not a stale/replayed one.
     */
    public function authorizeUrl(Request $request, MailAccount $account, MailOAuthProvider $provider): string
    {
        $nonce = Str::random(40);
        $request->session()->put("mail-oauth-nonce-{$account->id}", $nonce);

        $state = encrypt(['mail_account_id' => $account->id, 'nonce' => $nonce]);

        $params = [
            'client_id' => $this->clientId($provider),
            'redirect_uri' => route('mail.oauth.callback', $provider->value),
            'response_type' => 'code',
            'scope' => $provider->scope(),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return $provider->authorizeUrl().'?'.http_build_query($params);
    }

    /**
     * Validates `state`, exchanges the callback's `code` for a token
     * pair, and merges the result into the account's config.
     * Microsoft always re-issues a refresh_token on every grant;
     * Google only hands one back on the very first consent (a later
     * re-consent omits it), so an existing refresh_token is kept
     * rather than overwritten with a missing one.
     */
    public function handleCallback(Request $request, MailOAuthProvider $routeProvider): MailAccount
    {
        $state = $this->decodeState($request);
        $account = MailAccount::findOrFail($state['mail_account_id']);

        $expectedNonce = $request->session()->pull("mail-oauth-nonce-{$account->id}");

        if ($expectedNonce === null || ! hash_equals($expectedNonce, (string) $state['nonce'])) {
            throw new RuntimeException(t('Sessione di autorizzazione OAuth non valida o scaduta.'));
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            throw new RuntimeException(t('Autorizzazione OAuth negata o annullata.'));
        }

        $provider = $account->auth_method->provider();

        if ($provider !== $routeProvider) {
            throw new RuntimeException(t('Il provider OAuth non corrisponde a quello configurato per questo account.'));
        }

        $data = $this->requestToken($provider, [
            'client_id' => $this->clientId($provider),
            'client_secret' => $this->clientSecret($provider),
            'code' => $code,
            'redirect_uri' => route('mail.oauth.callback', $provider->value),
            'grant_type' => 'authorization_code',
        ]);

        $config = $account->config ?? [];
        $config['oauth_provider'] = $provider->value;
        $config['access_token'] = $data['access_token'];
        $config['refresh_token'] = $data['refresh_token'] ?? ($config['refresh_token'] ?? null);
        $config['token_expires_at'] = now()->addSeconds((int) ($data['expires_in'] ?? 3600))->toIso8601String();

        if ($config['refresh_token'] === null) {
            throw new RuntimeException(t('Il provider non ha restituito un refresh token: ricollega l\'account autorizzando l\'accesso offline.'));
        }

        $account->config = $config;
        $account->save();

        return $account;
    }

    /**
     * Returns a still-valid access token, transparently refreshing it
     * first when it's expired (or close enough that it might expire
     * mid-request) — called by ImapMailReader/SmtpMailSender on every
     * connection for an OAuth account, never cached beyond that.
     */
    public function freshAccessToken(MailAccount $account): string
    {
        $config = $account->config ?? [];
        $provider = $account->auth_method->provider() ?? throw new RuntimeException(t('Questo account non usa OAuth.'));
        $expiresAt = isset($config['token_expires_at']) ? CarbonImmutable::parse($config['token_expires_at']) : null;

        if ($expiresAt !== null && $expiresAt->subMinute()->isFuture() && isset($config['access_token'])) {
            return $config['access_token'];
        }

        $refreshToken = $config['refresh_token'] ?? null;

        if ($refreshToken === null) {
            throw new RuntimeException(t('Sessione OAuth scaduta: ricollega l\'account dalla pagina "Le mie caselle".'));
        }

        $data = $this->requestToken($provider, [
            'client_id' => $this->clientId($provider),
            'client_secret' => $this->clientSecret($provider),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        $config['access_token'] = $data['access_token'];
        $config['token_expires_at'] = now()->addSeconds((int) ($data['expires_in'] ?? 3600))->toIso8601String();
        $config['refresh_token'] = $data['refresh_token'] ?? $refreshToken;

        $account->config = $config;
        $account->save();

        return $config['access_token'];
    }

    /**
     * @return array{mail_account_id: int, nonce: string}
     */
    private function decodeState(Request $request): array
    {
        $raw = $request->query('state');

        if (! is_string($raw) || $raw === '') {
            throw new RuntimeException(t('Richiesta OAuth priva del parametro state.'));
        }

        try {
            $state = decrypt($raw);
        } catch (\Throwable) {
            throw new RuntimeException(t('Parametro state OAuth non valido.'));
        }

        if (! is_array($state) || ! isset($state['mail_account_id'], $state['nonce'])) {
            throw new RuntimeException(t('Parametro state OAuth malformato.'));
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function requestToken(MailOAuthProvider $provider, array $payload): array
    {
        $response = Http::asForm()->post($provider->tokenUrl(), $payload);

        if ($response->failed()) {
            $detail = $response->json('error_description') ?? $response->json('error') ?? (string) $response->status();

            throw new RuntimeException(t('Il provider OAuth :provider ha rifiutato la richiesta: :detail', ['provider' => $provider->label(), 'detail' => $detail]));
        }

        return $response->json();
    }

    private function clientId(MailOAuthProvider $provider): ?string
    {
        return match ($provider) {
            MailOAuthProvider::Google => MailSetting::current()->google_oauth_client_id,
            MailOAuthProvider::Microsoft => MailSetting::current()->microsoft_oauth_client_id,
        };
    }

    private function clientSecret(MailOAuthProvider $provider): ?string
    {
        return match ($provider) {
            MailOAuthProvider::Google => MailSetting::current()->google_oauth_client_secret,
            MailOAuthProvider::Microsoft => MailSetting::current()->microsoft_oauth_client_secret,
        };
    }
}
