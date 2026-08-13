<?php

namespace Fazzinipierluigi\CrmCore\Http\Controllers\Auth;

use Fazzinipierluigi\CrmCore\Contracts\CrmUser;
use Fazzinipierluigi\CrmCore\Http\Controllers\Controller;
use Fazzinipierluigi\CrmCore\Models\LoginProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use OneLogin\Saml2\Auth as SamlAuth;
use OneLogin\Saml2\Error as SamlError;
use OneLogin\Saml2\Metadata;

class SamlLoginController extends Controller
{
    /**
     * Serve this provider's SP metadata XML, for the IdP administrator to
     * import when configuring the connection.
     */
    public function metadata(LoginProvider $provider): Response
    {
        abort_unless($provider->type === 'saml', 404);

        $settings = $this->authFor($provider)->getSettings();

        return response(Metadata::builder($settings->getSPData()), 200, [
            'Content-Type' => 'text/xml',
        ]);
    }

    /**
     * Start SP-initiated login: redirect to the IdP's SSO endpoint.
     */
    public function redirect(LoginProvider $provider): RedirectResponse
    {
        abort_unless($provider->is_active && $provider->type === 'saml', 404);

        $url = $this->authFor($provider)->login(route('dashboard'), [], false, false, true);

        return redirect()->away($url);
    }

    /**
     * Assertion Consumer Service: process the IdP's POSTed SAMLResponse and
     * log in the local user already linked to this provider (matched by
     * `provider_identifier`/NameID, falling back to an email attribute).
     */
    public function acs(LoginProvider $provider, Request $request): RedirectResponse
    {
        abort_unless($provider->is_active && $provider->type === 'saml', 404);

        $auth = $this->authFor($provider);

        try {
            $auth->processResponse();
        } catch (SamlError) {
            return redirect()->route('login')->with('error', trans('auth.provider_failed', ['provider' => $provider->name]));
        }

        if (! empty($auth->getErrors()) || ! $auth->isAuthenticated()) {
            return redirect()->route('login')->with('error', trans('auth.provider_failed', ['provider' => $provider->name]));
        }

        $user = $this->matchUser($provider, $auth);

        if (! $user) {
            return redirect()->route('login')->with('error', trans('auth.provider_unlinked', ['provider' => $provider->name]));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Build a SAML Auth instance from the provider's stored config.
     */
    private function authFor(LoginProvider $provider): SamlAuth
    {
        $config = $provider->config ?? [];

        return new SamlAuth([
            'strict' => true,
            'sp' => [
                'entityId' => ($config['sp_entity_id'] ?? null) ?: route('login.saml.metadata', $provider),
                'assertionConsumerService' => [
                    'url' => route('login.saml.acs', $provider),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                ],
            ],
            'idp' => [
                'entityId' => $config['idp_entity_id'] ?? '',
                'singleSignOnService' => [
                    'url' => $config['idp_sso_url'] ?? '',
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ],
                'x509cert' => $config['idp_x509_cert'] ?? '',
            ],
        ]);
    }

    /**
     * Find the local user linked to this provider whose identifier matches
     * the assertion — by `provider_identifier`/NameID first, falling back
     * to the mapped email attribute for users with no explicit identifier.
     */
    private function matchUser(LoginProvider $provider, SamlAuth $auth): ?CrmUser
    {
        $config = $provider->config ?? [];
        $nameId = $auth->getNameId();
        $email = $auth->getAttribute($config['attr_email'] ?? 'email')[0] ?? null;

        $userModel = config('crm.user_model');

        return $userModel::where('login_provider_id', $provider->id)
            ->where(function ($query) use ($nameId, $email) {
                $query->where('provider_identifier', $nameId);

                if ($email) {
                    $query->orWhere(function ($query) use ($email) {
                        $query->whereNull('provider_identifier')->where('email', $email);
                    });
                }
            })
            ->first();
    }
}
