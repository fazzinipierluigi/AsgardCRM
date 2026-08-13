<?php

use Fazzinipierluigi\AsgardCRM\Models\MailAccount;
use Fazzinipierluigi\AsgardCRM\Services\Mail\Pop3MailReader;
use Fazzinipierluigi\AsgardCRM\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * End-to-end: MailAccount config -> Pop3MailReader -> real loopback
 * POP3 server -> parsed MailMessageDTO. Reuses the fork-based fake
 * server helpers defined in Pop3ClientTest.php (Pest loads every test
 * file's top-level functions into the same global namespace).
 */
test('fetchMessage connects, retrieves and parses a real message end to end', function () {
    if (! function_exists('pcntl_fork')) {
        test()->markTestSkipped('pcntl extension not available.');
    }

    $account = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'pop3', 'name' => 'Vecchia', 'email_address' => 'me@example.com',
        'config' => ['host' => '127.0.0.1', 'port' => 0, 'encryption' => 'none', 'username' => 'me@example.com', 'password' => 'secret'],
    ]);

    $rawMessage = "Subject: Fattura di agosto\r\nFrom: Fornitore <fornitore@example.com>\r\nDate: Mon, 3 Aug 2026 10:00:00 +0200\r\n\r\nCorpo del messaggio.";

    ['pid' => $pid, 'port' => $port] = startFakePop3Server([
        'USER' => "+OK\r\n",
        'PASS' => "+OK\r\n",
        'UIDL' => "+OK\r\n1 uid-abc-123\r\n.\r\n",
        'RETR' => "+OK\r\n{$rawMessage}\r\n.\r\n",
        'QUIT' => "+OK\r\n",
    ]);

    $account->config = array_merge($account->config, ['port' => $port]);
    $account->save();

    try {
        $message = (new Pop3MailReader)->fetchMessage($account, 'INBOX', 'uid-abc-123');

        expect($message->subject)->toBe('Fattura di agosto');
        expect($message->fromAddress)->toBe('fornitore@example.com');
        expect($message->fromName)->toBe('Fornitore');
        expect($message->textBody)->toBe('Corpo del messaggio.');
        expect($message->date?->format('Y-m-d'))->toBe('2026-08-03');
    } finally {
        stopFakePop3Server($pid);
    }
});
