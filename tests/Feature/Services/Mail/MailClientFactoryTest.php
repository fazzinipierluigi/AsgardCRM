<?php

use Fazzinipierluigi\CrmCore\Models\MailAccount;
use Fazzinipierluigi\CrmCore\Models\MailConnector;
use App\Models\User;
use Fazzinipierluigi\CrmCore\Services\Mail\Exchange\EwsMailClient;
use Fazzinipierluigi\CrmCore\Services\Mail\Exchange\GraphMailClient;
use Fazzinipierluigi\CrmCore\Services\Mail\ImapMailReader;
use Fazzinipierluigi\CrmCore\Services\Mail\MailClientFactory;
use Fazzinipierluigi\CrmCore\Services\Mail\Pop3MailReader;
use Fazzinipierluigi\CrmCore\Services\Mail\SmtpMailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an imap account resolves to ImapMailReader', function () {
    $account = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'imap', 'name' => 'Lavoro', 'email_address' => 'x@example.com',
        'config' => ['host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'x@example.com', 'password' => 'shh'],
    ]);

    expect((new MailClientFactory)->readerFor($account))->toBeInstanceOf(ImapMailReader::class);
});

test('a pop3 account resolves to Pop3MailReader', function () {
    $account = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'pop3', 'name' => 'Vecchia', 'email_address' => 'x@example.com',
        'config' => ['host' => 'pop3.example.com', 'port' => 995, 'encryption' => 'ssl', 'username' => 'x@example.com', 'password' => 'shh'],
    ]);

    expect((new MailClientFactory)->readerFor($account))->toBeInstanceOf(Pop3MailReader::class);
});

test('a direct exchange account (no shared connector) resolves to EwsMailClient', function () {
    $exchange = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'exchange', 'name' => 'Diretto', 'email_address' => 'x@example.com',
        'config' => ['ews_url' => 'https://mail.example.com/EWS/Exchange.asmx', 'username' => 'x', 'password' => 'shh'],
    ]);

    expect((new MailClientFactory)->readerFor($exchange))->toBeInstanceOf(EwsMailClient::class);
});

test('an exchange account on a shared graph connector resolves to GraphMailClient', function () {
    $connector = MailConnector::create(['type' => 'exchange_graph', 'name' => 'Aziendale', 'slug' => 'aziendale', 'is_active' => true]);
    $exchange = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'exchange', 'name' => 'Aziendale', 'email_address' => 'x@example.com',
        'mail_connector_id' => $connector->id,
    ]);

    expect((new MailClientFactory)->readerFor($exchange))->toBeInstanceOf(GraphMailClient::class);
});

test('an exchange account on a shared ews connector resolves to EwsMailClient', function () {
    $connector = MailConnector::create([
        'type' => 'exchange_ews', 'name' => 'Aziendale EWS', 'slug' => 'aziendale-ews', 'is_active' => true,
        'config' => ['ews_url' => 'https://mail.example.com/EWS/Exchange.asmx', 'username' => 'svc', 'password' => 'shh'],
    ]);
    $exchange = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'exchange', 'name' => 'Aziendale', 'email_address' => 'x@example.com',
        'mail_connector_id' => $connector->id,
    ]);

    expect((new MailClientFactory)->readerFor($exchange))->toBeInstanceOf(EwsMailClient::class);
});

test('senderFor: imap and pop3 accounts resolve to SmtpMailSender', function () {
    $imap = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'imap', 'name' => 'Lavoro', 'email_address' => 'x@example.com',
        'config' => ['host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'x@example.com', 'password' => 'shh', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587],
    ]);

    expect((new MailClientFactory)->senderFor($imap))->toBeInstanceOf(SmtpMailSender::class);
});

test('senderFor: a direct exchange account sends through EwsMailClient too', function () {
    $exchange = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'exchange', 'name' => 'Diretto', 'email_address' => 'x@example.com',
        'config' => ['ews_url' => 'https://mail.example.com/EWS/Exchange.asmx', 'username' => 'x', 'password' => 'shh'],
    ]);

    expect((new MailClientFactory)->senderFor($exchange))->toBeInstanceOf(EwsMailClient::class);
});

test('senderFor: a shared graph connector account sends through GraphMailClient, not SMTP', function () {
    $connector = MailConnector::create(['type' => 'exchange_graph', 'name' => 'Aziendale', 'slug' => 'aziendale-sender', 'is_active' => true]);
    $exchange = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'exchange', 'name' => 'Aziendale', 'email_address' => 'x@example.com',
        'mail_connector_id' => $connector->id,
    ]);

    expect((new MailClientFactory)->senderFor($exchange))->toBeInstanceOf(GraphMailClient::class);
});
