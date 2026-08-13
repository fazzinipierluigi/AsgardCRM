<?php

namespace Fazzinipierluigi\AsgardCRM\Http\Controllers\Admin;

use Fazzinipierluigi\AsgardCRM\Http\Controllers\Controller;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\StoreLoginProviderRequest;
use Fazzinipierluigi\AsgardCRM\Http\Requests\Admin\UpdateLoginProviderRequest;
use Fazzinipierluigi\AsgardCRM\Models\LoginProvider;
use Fazzinipierluigi\LaraccoonDatasource\EloquentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginProviderController extends Controller
{
    /**
     * Display the login providers listing page.
     */
    public function index(): View
    {
        return view('crm::admin.login-providers.index');
    }

    /**
     * Serve the server-side datatable request for the login providers listing.
     */
    public function data(Request $request): JsonResponse
    {
        $providers = LoginProvider::query()->select('id', 'type', 'name', 'slug', 'is_active', 'is_system', 'created_at');

        $source = new EloquentSource;
        $source->apply($providers, $request, null, ['name', 'type', 'slug']);

        return $source->getResponse(function (LoginProvider $provider) {
            return [
                'id' => $provider->id,
                'type' => $provider->type,
                'name' => $provider->name,
                'slug' => $provider->slug,
                'is_active' => $provider->is_active,
                'is_system' => $provider->is_system,
                'created_at' => $provider->created_at->format('d/m/Y H:i'),
            ];
        });
    }

    /**
     * Show the form to create a new login provider.
     */
    public function create(): View
    {
        return view('crm::admin.login-providers.create');
    }

    /**
     * Persist a new login provider. The slug is auto-generated from the name.
     */
    public function store(StoreLoginProviderRequest $request): RedirectResponse
    {
        LoginProvider::create([
            'type' => $request->string('type'),
            'name' => $request->string('name'),
            'slug' => LoginProvider::uniqueSlug($request->string('name')),
            'is_active' => $request->boolean('is_active'),
            'config' => $this->configFor($request, $request->string('type')->value()),
        ]);

        return redirect()->route('admin.login-providers.index')->with('status', 'login-provider-created');
    }

    /**
     * Show the form to edit an existing login provider.
     */
    public function edit(LoginProvider $loginProvider): View|RedirectResponse
    {
        if ($loginProvider->is_system) {
            return redirect()->route('admin.login-providers.index')->with('error', 'Il provider locale non può essere modificato.');
        }

        return view('crm::admin.login-providers.edit', ['loginProvider' => $loginProvider]);
    }

    /**
     * Update an existing login provider.
     */
    public function update(UpdateLoginProviderRequest $request, LoginProvider $loginProvider): RedirectResponse
    {
        if ($loginProvider->is_system) {
            return back()->with('error', 'Il provider locale non può essere modificato.');
        }

        $config = $this->configFor($request, $loginProvider->type);

        // Leaving a secret field blank on edit keeps the previously stored
        // value. Laravel's ConvertEmptyStringsToNull middleware turns the
        // blank input into null before it reaches here.
        foreach (['bind_password', 'client_secret'] as $secret) {
            if (array_key_exists($secret, $config) && $config[$secret] === null) {
                $config[$secret] = $loginProvider->config[$secret] ?? null;
            }
        }

        $loginProvider->name = $request->string('name');
        $loginProvider->is_active = $request->boolean('is_active');
        $loginProvider->config = $config;
        $loginProvider->save();

        return redirect()->route('admin.login-providers.index')->with('status', 'login-provider-updated');
    }

    /**
     * Delete a login provider.
     */
    public function destroy(LoginProvider $loginProvider): RedirectResponse
    {
        if ($loginProvider->is_system) {
            return back()->with('error', 'Non è possibile eliminare un provider di sistema.');
        }

        $loginProvider->delete();

        return redirect()->route('admin.login-providers.index')->with('status', 'login-provider-deleted');
    }

    /**
     * Build the type-specific config array from the request, keeping only
     * the fields relevant to the given provider type.
     *
     * @return array<string, mixed>
     */
    private function configFor(Request $request, string $type): array
    {
        $fields = match ($type) {
            'ldap' => ['host', 'port', 'base_dn', 'bind_dn', 'bind_password', 'use_tls', 'user_filter', 'attr_username', 'attr_email', 'attr_name'],
            'oauth', 'oidc' => ['client_id', 'client_secret', 'authorize_url', 'token_url', 'userinfo_url', 'scopes'],
            'saml' => ['idp_entity_id', 'idp_sso_url', 'idp_x509_cert', 'sp_entity_id', 'attr_email', 'attr_name'],
            default => [],
        };

        $config = $request->only($fields);

        if (array_key_exists('use_tls', $config)) {
            $config['use_tls'] = $request->boolean('use_tls');
        }

        return $config;
    }
}
