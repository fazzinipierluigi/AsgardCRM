<?php

use Fazzinipierluigi\CrmCore\Models\MailAccount;
use Fazzinipierluigi\CrmCore\Models\MailSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function googleAccountFor(User $user, array $config = []): MailAccount
{
    return MailAccount::create([
        'user_id' => $user->id, 'protocol' => 'imap', 'auth_method' => 'google_oauth', 'name' => 'Gmail', 'email_address' => 'me@gmail.com',
        'config' => $config,
    ]);
}

test('guests cannot start or complete the oauth flow', function () {
    $account = googleAccountFor(User::factory()->create());

    $this->get(route('mail.oauth.connect', [$account, 'google']))->assertRedirect(route('login'));
    $this->get(route('mail.oauth.callback', 'google'))->assertRedirect(route('login'));
});

test('connect redirects to the provider consent screen once it is configured', function () {
    MailSetting::current()->update(['google_oauth_client_id' => 'id', 'google_oauth_client_secret' => 'secret']);
    $user = User::factory()->create();
    $account = googleAccountFor($user);

    $response = $this->actingAs($user)->get(route('mail.oauth.connect', [$account, 'google']));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?');
});

test('connect redirects back with an error when the provider has no client credentials configured', function () {
    $user = User::factory()->create();
    $account = googleAccountFor($user);

    $response = $this->actingAs($user)->get(route('mail.oauth.connect', [$account, 'google']));

    $response->assertRedirect(route('mail.accounts.edit', $account));
    $response->assertSessionHasErrors(['auth_method']);
});

test('a user cannot start the oauth flow for another user\'s account', function () {
    $account = googleAccountFor(User::factory()->create());

    $this->actingAs(User::factory()->create())->get(route('mail.oauth.connect', [$account, 'google']))->assertForbidden();
});

test('connect rejects a provider that does not match the account\'s configured auth_method', function () {
    $user = User::factory()->create();
    $account = googleAccountFor($user);

    $this->actingAs($user)->get(route('mail.oauth.connect', [$account, 'microsoft']))->assertStatus(422);
});

test('callback stores the tokens and redirects to the edit page on success', function () {
    Http::fake(['oauth2.googleapis.com/*' => Http::response([
        'access_token' => 'access-123', 'refresh_token' => 'refresh-456', 'expires_in' => 3600,
    ], 200)]);
    MailSetting::current()->update(['google_oauth_client_id' => 'id', 'google_oauth_client_secret' => 'secret']);
    $user = User::factory()->create();
    $account = googleAccountFor($user);

    // Drive it through connect() first so the session nonce it stashes
    // is the same one the callback below must match — this is what the
    // real Google/Microsoft redirect round trip does with the browser's
    // own session cookie.
    $this->actingAs($user)->get(route('mail.oauth.connect', [$account, 'google']));

    $state = encrypt(['mail_account_id' => $account->id, 'nonce' => session("mail-oauth-nonce-{$account->id}")]);

    $response = $this->actingAs($user)->get(route('mail.oauth.callback', 'google').'?'.http_build_query(['code' => 'auth-code', 'state' => $state]));

    $response->assertRedirect(route('mail.accounts.edit', $account));
    expect($account->fresh()->isOAuthConnected())->toBeTrue();
});

test('callback redirects to the accounts index with an error on failure', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('mail.oauth.callback', 'google').'?'.http_build_query(['error' => 'access_denied']));

    $response->assertRedirect(route('mail.accounts.index'));
    $response->assertSessionHasErrors(['auth_method']);
});
