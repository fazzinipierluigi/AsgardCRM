<?php

use Fazzinipierluigi\CrmCore\Models\MailAccount;
use Fazzinipierluigi\CrmCore\Models\MailConnector;
use App\Models\User;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailComposeAttachmentDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailComposeDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\Exchange\GraphMailClient;
use Fazzinipierluigi\CrmCore\Services\Mail\Exchange\MailGraphTokenClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function graphMailAccount(): MailAccount
{
    $connector = MailConnector::create([
        'type' => 'exchange_graph', 'name' => 'Aziendale', 'slug' => 'aziendale-'.uniqid(), 'is_active' => true,
        'config' => ['tenant_id' => 'tenant-1', 'client_id' => 'client-1', 'client_secret' => 'secret-1'],
    ]);

    return MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'exchange', 'name' => 'Aziendale', 'email_address' => 'mario@example.com',
        'mail_connector_id' => $connector->id,
    ]);
}

test('listFolders maps the Graph mailFolders response', function () {
    $account = graphMailAccount();
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
        'graph.microsoft.com/v1.0/users/*/mailFolders/*/childFolders*' => Http::response(['value' => []], 200),
        'graph.microsoft.com/v1.0/users/*/mailFolders*' => Http::response([
            'value' => [
                ['id' => 'folder-inbox', 'displayName' => 'Posta in arrivo', 'childFolderCount' => 0],
                ['id' => 'folder-sent', 'displayName' => 'Posta inviata', 'childFolderCount' => 2],
            ],
        ], 200),
    ]);

    $folders = (new GraphMailClient(new MailGraphTokenClient))->listFolders($account);

    expect($folders)->toHaveCount(2);
    expect($folders[0]->path)->toBe('folder-inbox');
    expect($folders[0]->parentPath)->toBeNull();
    expect($folders[1]->hasChildren)->toBeTrue();
});

test('listFolders recurses into childFolders and links them via parentPath', function () {
    $account = graphMailAccount();
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
        'graph.microsoft.com/v1.0/users/*/mailFolders/folder-sent/childFolders*' => Http::response([
            'value' => [
                ['id' => 'folder-sent-2026', 'displayName' => '2026', 'childFolderCount' => 0],
            ],
        ], 200),
        'graph.microsoft.com/v1.0/users/*/mailFolders*' => Http::response([
            'value' => [
                ['id' => 'folder-inbox', 'displayName' => 'Posta in arrivo', 'childFolderCount' => 0],
                ['id' => 'folder-sent', 'displayName' => 'Posta inviata', 'childFolderCount' => 1],
            ],
        ], 200),
    ]);

    $folders = (new GraphMailClient(new MailGraphTokenClient))->listFolders($account);

    expect($folders)->toHaveCount(3);
    expect($folders[2]->path)->toBe('folder-sent-2026');
    expect($folders[2]->parentPath)->toBe('folder-sent');
});

test('listMessages maps items and the odata count', function () {
    $account = graphMailAccount();
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
        'graph.microsoft.com/v1.0/users/*/mailFolders/*/messages*' => Http::response([
            '@odata.count' => 1,
            'value' => [
                [
                    'id' => 'msg-1', 'subject' => 'Ciao', 'hasAttachments' => false, 'isRead' => false,
                    'sentDateTime' => '2026-08-10T09:00:00Z',
                    'from' => ['emailAddress' => ['name' => 'Cliente', 'address' => 'cliente@example.com']],
                ],
            ],
        ], 200),
    ]);

    $result = (new GraphMailClient(new MailGraphTokenClient))->listMessages($account, 'folder-inbox', 1, 25);

    expect($result['total'])->toBe(1);
    expect($result['items'][0]->uid)->toBe('msg-1');
    expect($result['items'][0]->fromAddress)->toBe('cliente@example.com');
    expect($result['items'][0]->isRead)->toBeFalse();
});

test('fetchMessage maps the full message with attachments', function () {
    $account = graphMailAccount();
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
        'graph.microsoft.com/v1.0/users/*/messages/msg-1*' => Http::response([
            'id' => 'msg-1', 'subject' => 'Ciao', 'sentDateTime' => '2026-08-10T09:00:00Z',
            'from' => ['emailAddress' => ['name' => 'Cliente', 'address' => 'cliente@example.com']],
            'toRecipients' => [['emailAddress' => ['address' => 'me@example.com']]],
            'body' => ['contentType' => 'html', 'content' => '<p>ciao</p>'],
            'internetMessageId' => '<abc@example.com>',
            'attachments' => [
                ['id' => 'attach-1', 'name' => 'documento.pdf', 'contentType' => 'application/pdf', 'size' => 1024],
            ],
        ], 200),
    ]);

    $message = (new GraphMailClient(new MailGraphTokenClient))->fetchMessage($account, 'folder-inbox', 'msg-1');

    expect($message->htmlBody)->toBe('<p>ciao</p>');
    expect($message->toAddresses)->toBe(['me@example.com']);
    expect($message->attachments)->toHaveCount(1);
    expect($message->attachments[0]->id)->toBe('attach-1');
});

test('fetchAttachment decodes contentBytes', function () {
    $account = graphMailAccount();
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
        'graph.microsoft.com/v1.0/users/*/messages/msg-1/attachments/attach-1*' => Http::response([
            'id' => 'attach-1', 'name' => 'documento.pdf', 'contentType' => 'application/pdf',
            'contentBytes' => base64_encode('contenuto del file'),
        ], 200),
    ]);

    $attachment = (new GraphMailClient(new MailGraphTokenClient))->fetchAttachment($account, 'folder-inbox', 'msg-1', 'attach-1');

    expect($attachment->contents)->toBe('contenuto del file');
    expect($attachment->filename)->toBe('documento.pdf');
});

test('send posts a sendMail payload with recipients and base64 attachments', function () {
    $account = graphMailAccount();
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'fake-token', 'expires_in' => 3600], 200),
        'graph.microsoft.com/v1.0/users/*/sendMail' => Http::response('', 202),
    ]);

    $compose = new MailComposeDTO(
        to: ['a@example.com'], cc: ['b@example.com'], bcc: [], subject: 'Ciao', bodyHtml: '<p>Corpo</p>',
        attachments: [new MailComposeAttachmentDTO('nota.txt', 'text/plain', 'contenuto')],
    );

    (new GraphMailClient(new MailGraphTokenClient))->send($account, $compose);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/sendMail')) {
            return false;
        }

        $body = $request->data();

        return $body['message']['subject'] === 'Ciao'
            && $body['message']['toRecipients'][0]['emailAddress']['address'] === 'a@example.com'
            && $body['message']['ccRecipients'][0]['emailAddress']['address'] === 'b@example.com'
            && $body['message']['attachments'][0]['contentBytes'] === base64_encode('contenuto')
            && $body['saveToSentItems'] === true;
    });
});

test('testConnection reports failure without throwing when Graph rejects the token', function () {
    $account = graphMailAccount();
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 401),
    ]);

    $result = (new GraphMailClient(new MailGraphTokenClient))->testConnection($account);

    expect($result['ok'])->toBeFalse();
});
