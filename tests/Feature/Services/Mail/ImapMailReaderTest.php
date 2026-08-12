<?php

use Fazzinipierluigi\CrmCore\Models\MailAccount;
use Fazzinipierluigi\CrmCore\Models\MailSetting;
use App\Models\User;
use Fazzinipierluigi\CrmCore\Services\Mail\ImapMailReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Real-socket IMAP protocol correctness isn't exercised in CI (no live
 * mailbox available) — this only proves MailAccount's decrypted config
 * maps onto webklex/php-imap's own account config shape correctly.
 * See ImapMailReader::clientConfig()'s docblock.
 */
test('account config maps onto the imap client config shape', function () {
    MailSetting::current()->update(['connection_timeout_seconds' => 15]);
    $account = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'imap', 'name' => 'Lavoro', 'email_address' => 'x@example.com',
        'config' => ['host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'x@example.com', 'password' => 'shh'],
    ]);

    $config = (new ImapMailReader)->clientConfig($account);

    expect($config)->toMatchArray([
        'host' => 'imap.example.com',
        'port' => 993,
        'protocol' => 'imap',
        'encryption' => 'ssl',
        'validate_cert' => true,
        'username' => 'x@example.com',
        'password' => 'shh',
        'timeout' => 15,
    ]);
});

test('encryption "none" maps to false, matching webklex\'s own convention', function () {
    $account = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'imap', 'name' => 'Locale', 'email_address' => 'x@example.com',
        'config' => ['host' => 'localhost', 'port' => 143, 'encryption' => 'none', 'username' => 'x@example.com', 'password' => 'shh'],
    ]);

    $config = (new ImapMailReader)->clientConfig($account);

    expect($config['encryption'])->toBeFalse();
});

test('a google oauth account maps to the provider\'s well-known host and sends the access token as password with authentication=oauth', function () {
    $account = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'imap', 'auth_method' => 'google_oauth', 'name' => 'Gmail', 'email_address' => 'me@gmail.com',
        'config' => ['oauth_provider' => 'google', 'access_token' => 'fresh-token', 'refresh_token' => 'refresh-me', 'token_expires_at' => now()->addHour()->toIso8601String()],
    ]);

    $config = (new ImapMailReader)->clientConfig($account);

    expect($config)->toMatchArray([
        'host' => 'imap.gmail.com',
        'port' => 993,
        'protocol' => 'imap',
        'encryption' => 'ssl',
        'username' => 'me@gmail.com',
        'password' => 'fresh-token',
        'authentication' => 'oauth',
    ]);
});
