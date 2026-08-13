<?php

namespace Fazzinipierluigi\AsgardCRM\Services\Connectors\Exchange;

use Fazzinipierluigi\AsgardCRM\Models\Connector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OAuth2 client-credentials ("app-only") token acquisition for
 * Microsoft Graph — one admin-configured app registration
 * (tenant/client id/secret, with application permission
 * Calendars.ReadWrite and admin consent) syncs every mapped mailbox,
 * with no per-user delegated consent flow. Same raw Http-facade style
 * as SocialLoginController, since there's no Graph SDK dependency here.
 */
class GraphTokenClient
{
    private const TOKEN_URL = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';

    /**
     * @throws RuntimeException if Microsoft rejects the credentials
     */
    public function tokenFor(Connector $connector): string
    {
        $cacheKey = "connector.{$connector->id}.graph_token";
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
