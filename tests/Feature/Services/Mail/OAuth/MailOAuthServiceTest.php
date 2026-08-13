<?php

use Fazzinipierluigi\AsgardCRM\Enums\MailOAuthProvider;
use Fazzinipierluigi\AsgardCRM\Models\MailAccount;
use Fazzinipierluigi\AsgardCRM\Models\MailSetting;
use Fazzinipierluigi\AsgardCRM\Services\Mail\OAuth\MailOAuthService;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function googleOAuthAccount(array $config = []): MailAccount
{
    return MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'imap', 'auth_method' => 'google_oauth', 'name' => 'Gmail', 'email_address' => 'me@gmail.com',
        'config' => $config,
    ]);
}

beforeEach(function () {
    MailSetting::current()->update([
        'google_oauth_client_id' => 'google-client-id',
        'google_oauth_client_secret' => 'google-client-secret',
    ]);

    // MailOAuthService::authorizeUrl()/handleCallback() both call
    // route('mail.oauth.callback', ...) — real enough for the assertions
    // below without booting the full route file.
    if (! Route::has('mail.oauth.callback')) {
        Route::get('mail/oauth/{provider}/callback', fn () => null)->name('mail.oauth.callback');
    }
});

test('isConfigured is true only once both client id and secret are set', function () {
    $service = new MailOAuthService;

    expect($service->isConfigured(MailOAuthProvider::Google))->toBeTrue();
    expect($service->isConfigured(MailOAuthProvider::Microsoft))->toBeFalse();
});

test('authorizeUrl builds the provider consent URL and stashes a nonce in the session', function () {
    $account = googleOAuthAccount();
    $request = Request::create('/');
    $request->setLaravelSession($this->app['session.store']);

    $url = (new MailOAuthService)->authorizeUrl($request, $account, MailOAuthProvider::Google);

    expect($url)->toContain('https://accounts.google.com/o/oauth2/v2/auth?');
    expect($url)->toContain('client_id=google-client-id');
    expect($url)->toContain('access_type=offline');
    expect($request->session()->get("mail-oauth-nonce-{$account->id}"))->not->toBeNull();
});

test('handleCallback rejects a state whose nonce does not match the session', function () {
    $account = googleOAuthAccount();
    $request = Request::create('/', 'GET', ['state' => encrypt(['mail_account_id' => $account->id, 'nonce' => 'wrong']), 'code' => 'auth-code']);
    $request->setLaravelSession($this->app['session.store']);
    $request->session()->put("mail-oauth-nonce-{$account->id}", 'expected-nonce');

    (new MailOAuthService)->handleCallback($request, MailOAuthProvider::Google);
})->throws(RuntimeException::class, 'Sessione di autorizzazione OAuth non valida o scaduta.');

test('handleCallback rejects a route provider that does not match the account\'s own auth_method', function () {
    $account = googleOAuthAccount();
    $request = Request::create('/', 'GET', ['state' => encrypt(['mail_account_id' => $account->id, 'nonce' => 'n']), 'code' => 'auth-code']);
    $request->setLaravelSession($this->app['session.store']);
    $request->session()->put("mail-oauth-nonce-{$account->id}", 'n');

    (new MailOAuthService)->handleCallback($request, MailOAuthProvider::Microsoft);
})->throws(RuntimeException::class, 'Il provider OAuth non corrisponde a quello configurato per questo account.');

test('handleCallback exchanges the code for tokens and stores them on the account', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response([
        'access_token' => 'access-123', 'refresh_token' => 'refresh-456', 'expires_in' => 3600,
    ], 200)]);

    $account = googleOAuthAccount();
    $request = Request::create('/', 'GET', ['state' => encrypt(['mail_account_id' => $account->id, 'nonce' => 'n']), 'code' => 'auth-code']);
    $request->setLaravelSession($this->app['session.store']);
    $request->session()->put("mail-oauth-nonce-{$account->id}", 'n');

    $result = (new MailOAuthService)->handleCallback($request, MailOAuthProvider::Google);

    expect($result->config['access_token'])->toBe('access-123');
    expect($result->config['refresh_token'])->toBe('refresh-456');
    expect($result->isOAuthConnected())->toBeTrue();
});

test('handleCallback fails when the provider never hands back a refresh token', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'access-123', 'expires_in' => 3600], 200)]);

    $account = googleOAuthAccount();
    $request = Request::create('/', 'GET', ['state' => encrypt(['mail_account_id' => $account->id, 'nonce' => 'n']), 'code' => 'auth-code']);
    $request->setLaravelSession($this->app['session.store']);
    $request->session()->put("mail-oauth-nonce-{$account->id}", 'n');

    (new MailOAuthService)->handleCallback($request, MailOAuthProvider::Google);
})->throws(RuntimeException::class);

test('freshAccessToken returns the stored token without a network call while it is still valid', function () {
    Http::fake();
    $account = googleOAuthAccount(['access_token' => 'still-valid', 'refresh_token' => 'r', 'token_expires_at' => now()->addHour()->toIso8601String()]);

    $token = (new MailOAuthService)->freshAccessToken($account);

    expect($token)->toBe('still-valid');
    Http::assertNothingSent();
});

test('freshAccessToken refreshes an expired token and persists the new one', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'renewed', 'expires_in' => 3600], 200)]);
    $account = googleOAuthAccount(['access_token' => 'stale', 'refresh_token' => 'r', 'token_expires_at' => now()->subMinute()->toIso8601String()]);

    $token = (new MailOAuthService)->freshAccessToken($account);

    expect($token)->toBe('renewed');
    expect($account->fresh()->config['access_token'])->toBe('renewed');
    // Google doesn't always re-issue a refresh_token on refresh grants —
    // the existing one must be kept, not dropped.
    expect($account->fresh()->config['refresh_token'])->toBe('r');
});

test('freshAccessToken fails without ever calling the provider when there is no refresh token to fall back on', function () {
    Http::fake();
    $account = googleOAuthAccount(['access_token' => 'stale', 'token_expires_at' => now()->subMinute()->toIso8601String()]);

    (new MailOAuthService)->freshAccessToken($account);
})->throws(RuntimeException::class);
