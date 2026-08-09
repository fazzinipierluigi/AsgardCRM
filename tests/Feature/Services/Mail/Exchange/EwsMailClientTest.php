<?php

use App\Models\MailAccount;
use App\Models\MailConnector;
use App\Models\User;
use App\Services\Mail\DTO\MailComposeDTO;
use App\Services\Mail\Exchange\EwsMailClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function ewsDirectAccount(): MailAccount
{
    return MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'exchange', 'name' => 'Exchange diretto', 'email_address' => 'me@example.com',
        'config' => ['ews_url' => 'https://mail.example.com/EWS/Exchange.asmx', 'username' => 'me@example.com', 'password' => 'secret', 'use_ntlm' => false],
    ]);
}

test('listFolders maps to MailFolderDTO', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsMailFixture('find-folder-response'), 200, ['Content-Type' => 'text/xml'])]);

    $folders = (new EwsMailClient)->listFolders(ewsDirectAccount());

    expect($folders)->toHaveCount(3);
    expect($folders[0]->path)->toBe('folder-inbox');
    expect($folders[0]->name)->toBe('Posta in arrivo');
    expect($folders[2]->path)->toBe('folder-sent-2026');
    expect($folders[2]->parentPath)->toBe('folder-sent');
});

test('listMessages maps to MailMessageSummaryDTO', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsMailFixture('find-item-response'), 200, ['Content-Type' => 'text/xml'])]);

    $result = (new EwsMailClient)->listMessages(ewsDirectAccount(), 'folder-inbox', 1, 25);

    expect($result['total'])->toBe(1);
    expect($result['items'][0]->uid)->toBe('AAMk-msg-1');
    expect($result['items'][0]->subject)->toBe('Richiesta preventivo');
});

test('fetchMessage maps to a full MailMessageDTO with attachments', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsMailFixture('get-item-response'), 200, ['Content-Type' => 'text/xml'])]);

    $message = (new EwsMailClient)->fetchMessage(ewsDirectAccount(), 'folder-inbox', 'AAMk-msg-1');

    expect($message->htmlBody)->toBe('<p>Buongiorno, vorrei un preventivo.</p>');
    expect($message->attachments)->toHaveCount(1);
    expect($message->attachments[0]->id)->toBe('attach-1');
});

test('fetchAttachment returns the decoded bytes', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsMailFixture('get-attachment-response'), 200, ['Content-Type' => 'text/xml'])]);

    $attachment = (new EwsMailClient)->fetchAttachment(ewsDirectAccount(), 'folder-inbox', 'AAMk-msg-1', 'attach-1');

    expect($attachment->contents)->toBe('contenuto del file');
    expect($attachment->filename)->toBe('capitolato.pdf');
});

test('send builds a MailComposeDTO into an EWS CreateItem request', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsMailFixture('create-item-response'), 200, ['Content-Type' => 'text/xml'])]);

    $compose = new MailComposeDTO(
        to: ['a@example.com'], cc: [], bcc: [], subject: 'Ciao', bodyHtml: '<p>Corpo</p>', attachments: [],
    );

    (new EwsMailClient)->send(ewsDirectAccount(), $compose);

    Http::assertSent(fn ($request) => str_contains($request->body(), 'MessageDisposition="SendAndSaveCopy"') && str_contains($request->body(), 'a@example.com'));
});

test('testConnection reports failure on an EWS error without throwing', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(file_get_contents(base_path('tests/Fixtures/ews/error-access-denied.xml')), 200, ['Content-Type' => 'text/xml'])]);

    $result = (new EwsMailClient)->testConnection(ewsDirectAccount());

    expect($result['ok'])->toBeFalse();
});

test('a shared ews connector uses the connector credentials and impersonates the account email', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsMailFixture('find-folder-response'), 200, ['Content-Type' => 'text/xml'])]);

    $connector = MailConnector::create([
        'type' => 'exchange_ews', 'name' => 'Aziendale EWS', 'slug' => 'aziendale-ews-'.uniqid(), 'is_active' => true,
        'config' => ['ews_url' => 'https://mail.example.com/EWS/Exchange.asmx', 'username' => 'svc-mail@example.com', 'password' => 'secret'],
    ]);
    $account = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'exchange', 'name' => 'Aziendale', 'email_address' => 'mario@example.com',
        'mail_connector_id' => $connector->id,
    ]);

    (new EwsMailClient)->listFolders($account);

    Http::assertSent(fn ($request) => str_contains($request->body(), '<t:PrimarySmtpAddress>mario@example.com</t:PrimarySmtpAddress>'));
});
