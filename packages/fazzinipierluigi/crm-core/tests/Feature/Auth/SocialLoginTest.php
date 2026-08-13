<?php

use Fazzinipierluigi\CrmCore\Models\LoginProvider;
use Fazzinipierluigi\CrmCore\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function oauthProvider(array $overrides = []): LoginProvider
{
    return LoginProvider::create(array_merge([
        'type' => 'oauth',
        'name' => 'Google',
        'slug' => 'google',
        'is_active' => true,
        'config' => [
            'client_id' => 'abc',
            'client_secret' => 'shh',
            'authorize_url' => 'https://idp.example.com/authorize',
            'token_url' => 'https://idp.example.com/token',
            'userinfo_url' => 'https://idp.example.com/userinfo',
            'scopes' => 'email profile',
        ],
    ], $overrides));
}

test('redirect sends the browser to the provider authorize url with the expected params', function () {
    $provider = oauthProvider();

    $response = $this->get(route('login.social.redirect', $provider));

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://idp.example.com/authorize?');

    parse_str(parse_url($location, PHP_URL_QUERY), $query);
    expect($query['client_id'])->toBe('abc');
    expect($query['response_type'])->toBe('code');
    expect($query['redirect_uri'])->toBe(route('login.social.callback', $provider));
    expect($query['scope'])->toBe('email profile');
    expect($query['state'])->not->toBeEmpty();
});

test('oidc redirect adds the openid scope automatically', function () {
    $provider = oauthProvider(['type' => 'oidc', 'slug' => 'oidc-provider', 'config' => [
        'client_id' => 'abc',
        'client_secret' => 'shh',
        'authorize_url' => 'https://idp.example.com/authorize',
        'token_url' => 'https://idp.example.com/token',
        'userinfo_url' => 'https://idp.example.com/userinfo',
        'scopes' => 'email',
    ]]);

    $response = $this->get(route('login.social.redirect', $provider));

    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['scope'])->toBe('openid email');
});

test('callback logs in the user linked by provider_identifier', function () {
    $provider = oauthProvider();
    $user = User::factory()->create([
        'login_provider_id' => $provider->id,
        'provider_identifier' => 'google-sub-123',
    ]);

    Http::fake([
        'idp.example.com/token' => Http::response(['access_token' => 'fake-token']),
        'idp.example.com/userinfo' => Http::response(['sub' => 'google-sub-123', 'email' => 'someone-else@example.com']),
    ]);

    session()->put('login_provider_state.'.$provider->slug, 'valid-state');

    $response = $this->get(route('login.social.callback', $provider, false).'?code=abc123&state=valid-state');

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard'));
});

test('callback falls back to matching by email when provider_identifier is not set', function () {
    $provider = oauthProvider();
    $user = User::factory()->create([
        'login_provider_id' => $provider->id,
        'provider_identifier' => null,
        'email' => 'jane@example.com',
    ]);

    Http::fake([
        'idp.example.com/token' => Http::response(['access_token' => 'fake-token']),
        'idp.example.com/userinfo' => Http::response(['sub' => 'unrelated-sub', 'email' => 'jane@example.com']),
    ]);

    session()->put('login_provider_state.'.$provider->slug, 'valid-state');

    $this->get(route('login.social.callback', $provider, false).'?code=abc123&state=valid-state');

    $this->assertAuthenticatedAs($user);
});

test('callback rejects a userinfo response matching no linked account', function () {
    $provider = oauthProvider();

    Http::fake([
        'idp.example.com/token' => Http::response(['access_token' => 'fake-token']),
        'idp.example.com/userinfo' => Http::response(['sub' => 'nobody', 'email' => 'nobody@example.com']),
    ]);

    session()->put('login_provider_state.'.$provider->slug, 'valid-state');

    $response = $this->get(route('login.social.callback', $provider, false).'?code=abc123&state=valid-state');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    expect(session('error'))->not->toBeNull();
});

test('callback rejects a mismatched state parameter', function () {
    $provider = oauthProvider();
    User::factory()->create(['login_provider_id' => $provider->id, 'provider_identifier' => 'google-sub-123']);

    Http::fake();

    session()->put('login_provider_state.'.$provider->slug, 'valid-state');

    $response = $this->get(route('login.social.callback', $provider, false).'?code=abc123&state=tampered-state');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    Http::assertNothingSent();
});

test('callback rejects when the provider reports an error', function () {
    $provider = oauthProvider();

    session()->put('login_provider_state.'.$provider->slug, 'valid-state');

    $response = $this->get(route('login.social.callback', $provider, false).'?error=access_denied&state=valid-state');

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});
