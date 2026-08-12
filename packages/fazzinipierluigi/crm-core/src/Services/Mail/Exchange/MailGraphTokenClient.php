<?php

namespace Fazzinipierluigi\CrmCore\Services\Mail\Exchange;

use Fazzinipierluigi\CrmCore\Models\MailConnector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OAuth2 client-credentials ("app-only") token acquisition for
 * Microsoft Graph, scoped to a MailConnector — a standalone duplicate
 * of Fazzinipierluigi\CrmCore\Services\Connectors\Exchange\GraphTokenClient (calendar)
 * rather than a generalization of it: refactoring the shared class to
 * also accept a MailConnector would risk the working calendar-sync
 * path for a mail feature that doesn't need to touch it, at the cost
 * of ~30 duplicated lines. One admin-configured app registration
 * (tenant/client id/secret, with application permissions Mail.Read
 * and Mail.Send, admin consent granted) reads/sends for every
 * MailAccount mapped to it via mail_connector_id — no per-user
 * delegated OAuth consent.
 */
class MailGraphTokenClient
{
    private const TOKEN_URL = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';

    /**
     * @throws RuntimeException if Microsoft rejects the credentials
     */
    public function tokenFor(MailConnector $connector): string
    {
        $cacheKey = "mail_connector.{$connector->id}.graph_token";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $config = $connector->config ?? [];

        $response = Http::asForm()->post(sprintf(self::TOKEN_URL, $config['tenant_id'] ?? ''), [
            'grant_type' => 'client_credentials',
            'client_id' => $config['client_id'] ?? '',
            'client_secret' => $config['client_secret'] ?? '',
            'scope' => 'https://graph.microsoft.com/.default',
        ]);

        $token = $response->json('access_token');

        if ($response->failed() || ! is_string($token)) {
            throw new RuntimeException("Impossibile ottenere un token da Microsoft Graph: {$response->body()}");
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        Cache::put($cacheKey, $token, max(60, $expiresIn - 120));

        return $token;
    }
}
