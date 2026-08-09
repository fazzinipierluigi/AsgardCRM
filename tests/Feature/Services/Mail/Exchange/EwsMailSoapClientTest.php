<?php

use App\Services\Mail\Exchange\EwsMailSoapClient;
use Illuminate\Support\Facades\Http;

function ewsMailFixture(string $name): string
{
    return file_get_contents(base_path("tests/Fixtures/ews-mail/{$name}.xml"));
}

function ewsMailConfig(array $overrides = []): array
{
    return array_merge([
        'ews_url' => 'https://mail.example.com/EWS/Exchange.asmx',
        'username' => 'me@example.com',
        'password' => 'secret',
        'use_ntlm' => false,
    ], $overrides);
}

test('findFolders parses the folder tree', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsMailFixture('find-folder-response'), 200, ['Content-Type' => 'text/xml'])]);

    $folders = (new EwsMailSoapClient(ewsMailConfig()))->findFolders();

    expect($folders)->toHaveCount(3);
    expect($folders[0])->toBe(['id' => 'folder-inbox', 'name' => 'Posta in arrivo', 'hasChildren' => false, 'parentId' => 'msgfolderroot-guid']);
    expect($folders[1])->toBe(['id' => 'folder-sent', 'name' => 'Posta inviata', 'hasChildren' => true, 'parentId' => 'msgfolderroot-guid']);
    expect($folders[2])->toBe(['id' => 'folder-sent-2026', 'name' => '2026', 'hasChildren' => false, 'parentId' => 'folder-sent']);

    Http::assertSent(fn ($request) => str_contains($request->body(), '<m:FindFolder') && str_contains($request->body(), 'Traversal="Deep"') && str_contains($request->body(), 'Id="msgfolderroot"'));
});

test('findMessages parses a page of message summaries', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsMailFixture('find-item-response'), 200, ['Content-Type' => 'text/xml'])]);

    $result = (new EwsMailSoapClient(ewsMailConfig()))->findMessages('folder-inbox', 0, 25);

    expect($result['total'])->toBe(1);
    expect($result['items'][0]['id'])->toBe('AAMk-msg-1');
    expect($result['items'][0]['subject'])->toBe('Richiesta preventivo');
    expect($result['items'][0]['fromAddress'])->toBe('cliente@example.com');
    expect($result['items'][0]['hasAttachments'])->toBeTrue();
    expect($result['items'][0]['isRead'])->toBeFalse();

    Http::assertSent(fn ($request) => str_contains($request->body(), 'MaxEntriesReturned="25"') && str_contains($request->body(), 'Id="folder-inbox"'));
});

test('getItem parses the full message including attachments metadata', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsMailFixture('get-item-response'), 200, ['Content-Type' => 'text/xml'])]);

    $message = (new EwsMailSoapClient(ewsMailConfig()))->getItem('AAMk-msg-1');

    expect($message['subject'])->toBe('Richiesta preventivo');
    expect($message['bodyHtml'])->toBe('<p>Buongiorno, vorrei un preventivo.</p>');
    expect($message['toAddresses'])->toBe(['me@example.com']);
    expect($message['messageId'])->toBe('<abc@example.com>');
    expect($message['attachments'])->toHaveCount(1);
    expect($message['attachments'][0]['id'])->toBe('attach-1');
    expect($message['attachments'][0]['sizeBytes'])->toBe(2048);
});

test('getAttachment decodes the base64 content', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsMailFixture('get-attachment-response'), 200, ['Content-Type' => 'text/xml'])]);

    $attachment = (new EwsMailSoapClient(ewsMailConfig()))->getAttachment('attach-1');

    expect($attachment['filename'])->toBe('capitolato.pdf');
    expect($attachment['mimeType'])->toBe('application/pdf');
    expect($attachment['content'])->toBe('contenuto del file');
});

test('an EWS error throws', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(file_get_contents(base_path('tests/Fixtures/ews/error-access-denied.xml')), 200, ['Content-Type' => 'text/xml'])]);

    expect(fn () => (new EwsMailSoapClient(ewsMailConfig()))->findFolders())->toThrow(RuntimeException::class);
});

test('sendMessage builds a CreateItem with SendAndSaveCopy, recipients and attachments', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsMailFixture('create-item-response'), 200, ['Content-Type' => 'text/xml'])]);

    (new EwsMailSoapClient(ewsMailConfig()))->sendMessage(
        'Ciao',
        '<p>Corpo</p>',
        ['a@example.com'],
        ['b@example.com'],
        ['c@example.com'],
        [['filename' => 'nota.txt', 'mimeType' => 'text/plain', 'contents' => 'contenuto']],
        '<orig@example.com>',
    );

    Http::assertSent(function ($request) {
        $body = $request->body();

        return str_contains($body, 'MessageDisposition="SendAndSaveCopy"')
            && str_contains($body, '<t:ToRecipients><t:Mailbox><t:EmailAddress>a@example.com</t:EmailAddress></t:Mailbox></t:ToRecipients>')
            && str_contains($body, '<t:CcRecipients><t:Mailbox><t:EmailAddress>b@example.com</t:EmailAddress></t:Mailbox></t:CcRecipients>')
            && str_contains($body, '<t:BccRecipients><t:Mailbox><t:EmailAddress>c@example.com</t:EmailAddress></t:Mailbox></t:BccRecipients>')
            && str_contains($body, '<t:InReplyTo>&lt;orig@example.com&gt;</t:InReplyTo>')
            && str_contains($body, base64_encode('contenuto'));
    });
});

test('impersonation header is sent only when an SMTP address is given', function () {
    Http::fake(['mail.example.com/EWS/Exchange.asmx' => Http::response(ewsMailFixture('find-folder-response'), 200, ['Content-Type' => 'text/xml'])]);

    (new EwsMailSoapClient(ewsMailConfig()))->findFolders('mario@example.com');

    Http::assertSent(fn ($request) => str_contains($request->body(), '<t:PrimarySmtpAddress>mario@example.com</t:PrimarySmtpAddress>'));
});
