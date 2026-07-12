<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SocialLoginController extends Controller
{
    /**
     * Redirect to the OAuth2/OIDC provider's authorize endpoint.
     *
     * Providers are admin-configured rows with arbitrary endpoints, so this
     * builds the Authorization Code flow request by hand (via the Http
     * client) instead of going through Socialite's named-driver registry,
     * which only supports a fixed list of hardcoded providers.
     */
    public function redirect(LoginProvider $provider): RedirectResponse
    {
        abort_unless($provider->is_active && in_array($provider->type, ['oauth', 'oidc'], true), 404);

        $config = $provider->config ?? [];
        $state = Str::random(40);

        session()->put("login_provider_state.{$provider->slug}", $state);

        $scopes = $config['scopes'] ?? '';
        if ($provider->type === 'oidc' && ! str_contains($scopes, 'openid')) {
            $scopes = trim('openid '.$scopes);
        }

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $config['client_id'] ?? '',
            'redirect_uri' => route('login.social.callback', $provider),
            'scope' => $scopes,
            'state' => $state,
        ]);

        return redirect()->away(($config['authorize_url'] ?? '').'?'.$query);
    }

    /**
     * Handle the provider's callback: exchange the code for a token, fetch
     * the userinfo endpoint, and log in the local user already linked to
     * this provider (matched by `provider_identifier`/`sub`, falling back
     * to email). Unlinked accounts are rejected — this app doesn't
     * just-in-time provision users from an external IdP.
     */
    public function callback(LoginProvider $provider, Request $request): RedirectResponse
    {
        abort_unless($provider->is_active && in_array($provider->type, ['oauth', 'oidc'], true), 404);

        $config = $provider->config ?? [];
        $stateKey = "login_provider_state.{$provider->slug}";
        $expectedState = session()->pull($stateKey);

        if ($request->string('error')->isNotEmpty() || ! $expectedState || ! hash_equals($expectedState, (string) $request->string('state'))) {
            return redirect()->route('login')->with('error', trans('auth.provider_failed', ['provider' => $provider->name]));
        }

        $tokenResponse = Http::asForm()->post($config['token_url'] ?? '', [
            'grant_type' => 'authorization_code',
            'code' => $request->string('code')->value(),
            'redirect_uri' => route('login.social.callback', $provider),
            'client_id' => $config['client_id'] ?? '',
            'client_secret' => $config['client_secret'] ?? '',
        ]);

        if ($tokenResponse->failed() || ! $tokenResponse->json('access_token')) {
            return redirect()->route('login')->with('error', trans('auth.provider_failed', ['provider' => $provider->name]));
        }

        $userinfo = Http::withToken($tokenResponse->json('access_token'))
            ->get($config['userinfo_url'] ?? '')
            ->json() ?? [];

        $user = $this->matchUser($provider, $userinfo);

        if (! $user) {
            return redirect()->route('login')->with('error', trans('auth.provider_unlinked', ['provider' => $provider->name]));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Find the local user linked to this provider whose identifier matches
     * the userinfo response — by `provider_identifier`/`sub` first, falling
     * back to email for users with no explicit identifier set.
     *
     * @param  array<string, mixed>  $userinfo
     */
    private function matchUser(LoginProvider $provider, array $userinfo): ?User
    {
        $subject = $userinfo['sub'] ?? null;
        $email = $userinfo['email'] ?? null;

        return User::where('login_provider_id', $provider->id)
            ->where(function ($query) use ($subject, $email) {
                $query->where('provider_identifier', $subject);

                if ($email) {
                    $query->orWhere(function ($query) use ($email) {
                        $query->whereNull('provider_identifier')->where('email', $email);
                    });
                }
            })
            ->first();
    }
}
