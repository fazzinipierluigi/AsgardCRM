<?php

use App\Mail\ComposedMail;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use App\Models\MailAccount;
use App\Models\MailMessageCache;
use App\Models\MailSetting;
use App\Models\MailSignature;
use App\Models\User;
use App\Services\Mail\DTO\MailAttachmentSummaryDTO;
use App\Services\Mail\DTO\MailFolderDTO;
use App\Services\Mail\DTO\MailMessageDTO;
use App\Services\Mail\MailClientFactory;
use App\Services\Mail\MailReaderInterface;
use App\Services\Mail\Testing\FakeMailClient;
use Database\Seeders\EmailEntitySeeder;
use Fazzinipierluigi\JustAGate\Models\Permission;
use Fazzinipierluigi\JustAGate\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Binds MailClientFactory::readerFor() to always return a single
 * FakeMailClient instance, regardless of the account's protocol —
 * lets these tests drive MailController without a real IMAP server.
 */
function bindFakeMailClient(): FakeMailClient
{
    $fake = new FakeMailClient;
    app()->instance(FakeMailClient::class, $fake);
    app()->bind(MailClientFactory::class, fn () => new class extends MailClientFactory
    {
        public function readerFor($account): MailReaderInterface
        {
            return app(FakeMailClient::class);
        }
    });

    return $fake;
}

function imapAccountFor(User $user): MailAccount
{
    return MailAccount::create([
        'user_id' => $user->id,
        'protocol' => 'imap',
        'name' => 'Lavoro',
        'email_address' => 'lavoro@example.com',
        'config' => [
            'host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'lavoro@example.com', 'password' => 'shh',
            'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_encryption' => 'starttls', 'smtp_username' => 'lavoro@example.com', 'smtp_password' => 'smtp-shh',
        ],
    ]);
}

test('guests are redirected to login', function () {
    $this->get(route('mail.index'))->assertRedirect(route('login'));
});

test('a user with no accounts is redirected to account creation', function () {
    $this->actingAs(User::factory()->create())->get(route('mail.index'))->assertRedirect(route('mail.accounts.create'));
});

test('page 1 message listings are served from the persisted header cache and only pick up changes via refresh=1', function () {
    $fake = bindFakeMailClient();
    $user = User::factory()->create();
    $account = imapAccountFor($user);

    $fake->seedFolders($account, [new MailFolderDTO('INBOX', 'Posta in arrivo')]);
    $fake->seedMessages($account, 'INBOX', [
        new MailMessageDTO('1', 'Primo messaggio', 'a@example.com', 'A', [], now()->toImmutable(), null, 'testo', null, []),
    ]);

    $first = $this->actingAs($user)->getJson(route('mail.messages', $account).'?folder=INBOX');
    $first->assertOk();
    expect($first->json('total'))->toBe(1);
    expect($first->json('cached'))->toBeFalse();
    expect(MailMessageCache::query()->where('mail_account_id', $account->id)->count())->toBe(1);

    // Mutate the fake mid-test — an un-refreshed request must keep
    // serving the persisted cache, not the mailbox's real current state.
    $fake->seedMessages($account, 'INBOX', [
        new MailMessageDTO('1', 'Primo messaggio', 'a@example.com', 'A', [], now()->toImmutable(), null, 'testo', null, []),
        new MailMessageDTO('2', 'Secondo messaggio', 'b@example.com', 'B', [], now()->toImmutable(), null, 'testo', null, []),
    ]);

    $second = $this->actingAs($user)->getJson(route('mail.messages', $account).'?folder=INBOX');
    expect($second->json('total'))->toBe(1);
    expect($second->json('cached'))->toBeTrue();

    // The silent background refresh mail.js fires after painting the
    // cached page (see resources/js/mail.js's loadMessages()) does pick
    // up the change, and re-persists it for the next un-refreshed request.
    $refreshed = $this->actingAs($user)->getJson(route('mail.messages', $account).'?folder=INBOX&refresh=1');
    expect($refreshed->json('total'))->toBe(2);
    expect($refreshed->json('cached'))->toBeFalse();

    $third = $this->actingAs($user)->getJson(route('mail.messages', $account).'?folder=INBOX');
    expect($third->json('total'))->toBe(2);
    expect($third->json('cached'))->toBeTrue();
});

test('page 2+ message listings use the short ephemeral cache instead of the persisted header cache', function () {
    MailSetting::current()->update(['cache_ttl_seconds' => 60]);
    $fake = bindFakeMailClient();
    $user = User::factory()->create();
    $account = imapAccountFor($user);

    $fake->seedFolders($account, [new MailFolderDTO('INBOX', 'Posta in arrivo')]);
    $fake->seedMessages($account, 'INBOX', [
        new MailMessageDTO('1', 'Uno', 'a@example.com', 'A', [], now()->toImmutable(), null, 'testo', null, []),
    ]);

    $first = $this->actingAs($user)->getJson(route('mail.messages', $account).'?folder=INBOX&page=2');
    $first->assertOk();
    expect($first->json('total'))->toBe(1);
    expect(MailMessageCache::query()->where('mail_account_id', $account->id)->count())->toBe(0);

    // Mutate mid-test — page 2 is still short-cached for
    // cache_ttl_seconds, so an immediate second request must not see it.
    $fake->seedMessages($account, 'INBOX', [
        new MailMessageDTO('1', 'Uno', 'a@example.com', 'A', [], now()->toImmutable(), null, 'testo', null, []),
        new MailMessageDTO('2', 'Due', 'b@example.com', 'B', [], now()->toImmutable(), null, 'testo', null, []),
    ]);

    $second = $this->actingAs($user)->getJson(route('mail.messages', $account).'?folder=INBOX&page=2');
    expect($second->json('total'))->toBe(1);
});

test('folders keep parentPath for nested folders and normalize a dangling parent to root', function () {
    $fake = bindFakeMailClient();
    $user = User::factory()->create();
    $account = imapAccountFor($user);

    $fake->seedFolders($account, [
        // Exchange's own root folder id is a valid EWS/Graph
        // parentPath but never a row itself — this must come back
        // normalized to null, not left dangling.
        new MailFolderDTO('INBOX', 'Posta in arrivo', false, 'msgfolderroot-guid'),
        new MailFolderDTO('INBOX.Archivio', 'Archivio', true, 'INBOX'),
        new MailFolderDTO('INBOX.Archivio.2026', '2026', false, 'INBOX.Archivio'),
    ]);

    $response = $this->actingAs($user)->getJson(route('mail.folders', $account));

    $response->assertOk();
    expect($response->json('folders'))->toBe([
        ['path' => 'INBOX', 'name' => 'Posta in arrivo', 'hasChildren' => false, 'parentPath' => null],
        ['path' => 'INBOX.Archivio', 'name' => 'Archivio', 'hasChildren' => true, 'parentPath' => 'INBOX'],
        ['path' => 'INBOX.Archivio.2026', 'name' => '2026', 'hasChildren' => false, 'parentPath' => 'INBOX.Archivio'],
    ]);
});

test('a user cannot browse another user\'s mail account', function () {
    bindFakeMailClient();
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $account = imapAccountFor($owner);

    $this->actingAs($intruder)->getJson(route('mail.folders', $account))->assertForbidden();
    $this->actingAs($intruder)->getJson(route('mail.messages', $account).'?folder=INBOX')->assertForbidden();
});

test('a reader failure surfaces as a clean JSON error, not a bare 500', function () {
    bindFakeMailClient();
    $user = User::factory()->create();
    $account = imapAccountFor($user);

    // No messages seeded in INBOX for this account — FakeMailClient::
    // fetchMessage() throws, exercising MailController::withReaderErrorHandling()
    // exactly like a real mail-server failure (e.g. Gmail rejecting a
    // plain-password IMAP login) would.
    $response = $this->actingAs($user)->getJson(route('mail.messages.show', $account).'?folder=INBOX&uid=999');

    $response->assertStatus(502);
    expect($response->json('error'))->toContain('not found');
});

test('fetching a message returns its live body and attachments', function () {
    $fake = bindFakeMailClient();
    $user = User::factory()->create();
    $account = imapAccountFor($user);

    $fake->seedMessages($account, 'INBOX', [
        new MailMessageDTO(
            uid: '1', subject: 'Ciao', fromAddress: 'a@example.com', fromName: 'A',
            toAddresses: ['me@example.com'], date: now()->toImmutable(), messageId: '<abc@example.com>',
            textBody: 'testo semplice', htmlBody: '<p>ciao</p>',
            attachments: [new MailAttachmentSummaryDTO('0', 'documento.pdf', 'application/pdf', 1024)],
        ),
    ]);

    $response = $this->actingAs($user)->getJson(route('mail.messages.show', $account).'?folder=INBOX&uid=1');

    $response->assertOk();
    expect($response->json('subject'))->toBe('Ciao');
    expect($response->json('html_body'))->toBe('<p>ciao</p>');
    expect($response->json('attachments.0.filename'))->toBe('documento.pdf');
});

test('attachment download is refused past the configured size limit', function () {
    MailSetting::current()->update(['max_attachment_size_kb' => 1]);
    $fake = bindFakeMailClient();
    $user = User::factory()->create();
    $account = imapAccountFor($user);

    $fake->seedMessages($account, 'INBOX', [
        new MailMessageDTO(
            uid: '1', subject: 'Ciao', fromAddress: 'a@example.com', fromName: 'A',
            toAddresses: [], date: now()->toImmutable(), messageId: null, textBody: null, htmlBody: null,
            attachments: [new MailAttachmentSummaryDTO('0', 'grande.zip', 'application/zip', 5 * 1024 * 1024)],
        ),
    ]);

    $url = route('mail.messages.attachment', $account).'?folder=INBOX&uid=1&attachment_id=0';

    $this->actingAs($user)->get($url)->assertStatus(413);
});

test('attaching a message creates one entity_email record and links via the existing relation system', function () {
    test()->seed(EmailEntitySeeder::class);
    $fake = bindFakeMailClient();
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Operatore', 'slug' => 'operatore-'.uniqid()]);
    $role->givePermission(Permission::where('key', 'entity_email.create')->firstOrFail());
    $role->givePermission(Permission::where('key', 'entity_email.index')->firstOrFail());
    $user->assignRole($role);
    $account = imapAccountFor($user);

    $fake->seedMessages($account, 'INBOX', [
        new MailMessageDTO(
            uid: '1', subject: 'Preventivo richiesto', fromAddress: 'cliente@example.com', fromName: 'Cliente',
            toAddresses: ['me@example.com'], date: now()->toImmutable(), messageId: '<xyz@example.com>',
            textBody: 'testo', htmlBody: null, attachments: [],
        ),
    ]);

    $response = $this->actingAs($user)->postJson(route('mail.attach', $account), ['folder' => 'INBOX', 'uid' => '1']);

    $response->assertOk();
    $emailEntity = Entity::where('slug', 'email')->firstOrFail();
    $record = EntityRecord::forEntity($emailEntity)->newQuery()->findOrFail($response->json('record_id'));
    expect($record->oggetto)->toBe('Preventivo richiesto');
    expect($record->mail_account_id)->toBe($account->id);
    expect($record->folder)->toBe('INBOX');
    expect($record->message_uid)->toBe('1');

    // Repeating the same attach is idempotent: no duplicate row.
    $this->actingAs($user)->postJson(route('mail.attach', $account), ['folder' => 'INBOX', 'uid' => '1'])
        ->assertJson(['record_id' => $record->id]);
    expect(EntityRecord::forEntity($emailEntity)->newQuery()->count())->toBe(1);
});

test('attaching a message requires entity_email.create permission', function () {
    test()->seed(EmailEntitySeeder::class);
    bindFakeMailClient();
    $user = User::factory()->create();
    $account = imapAccountFor($user);

    $this->actingAs($user)->postJson(route('mail.attach', $account), ['folder' => 'INBOX', 'uid' => '1'])->assertForbidden();
});

test('compose page requires at least one mail account', function () {
    $this->actingAs(User::factory()->create())->get(route('mail.compose'))->assertNotFound();
});

test('compose page renders when the user has an account', function () {
    $user = User::factory()->create();
    imapAccountFor($user);

    $this->actingAs($user)->get(route('mail.compose'))->assertOk();
});

test('compose page embeds the account\'s signature already resolved for the current user', function () {
    $user = User::factory()->create(['name' => 'Anna Verdi', 'job_title' => 'Responsabile vendite']);
    $signature = MailSignature::create(['name' => 'Commerciale', 'body_html' => '<p>{{user.name}} — {{user.job_title}}</p>']);
    $account = imapAccountFor($user);
    $account->update(['mail_signature_id' => $signature->id]);

    $response = $this->actingAs($user)->get(route('mail.compose'));

    $response->assertOk();
    $response->assertSee('Anna Verdi', false);
    $response->assertSee('Responsabile vendite', false);
    $response->assertDontSee('{{user.name}}', false);
});

test('reply prefills subject with Re:, quotes the body and threads via message id', function () {
    $fake = bindFakeMailClient();
    $user = User::factory()->create();
    $account = imapAccountFor($user);

    $fake->seedMessages($account, 'INBOX', [
        new MailMessageDTO(
            uid: '1', subject: 'Preventivo', fromAddress: 'cliente@example.com', fromName: 'Cliente',
            toAddresses: ['me@example.com'], date: now()->toImmutable(), messageId: '<abc@example.com>',
            textBody: 'Vorrei un preventivo.', htmlBody: null, attachments: [],
        ),
    ]);

    $response = $this->actingAs($user)->getJson(route('mail.messages.reply', $account).'?folder=INBOX&uid=1');

    $response->assertOk();
    expect($response->json('to'))->toBe(['cliente@example.com']);
    expect($response->json('subject'))->toBe('Re: Preventivo');
    expect($response->json('in_reply_to'))->toBe('<abc@example.com>');
    expect($response->json('references'))->toBe('<abc@example.com>');
    expect($response->json('body_html'))->toContain('Vorrei un preventivo.');
});

test('forward prefills subject with Fwd:, quotes the body and does not thread', function () {
    $fake = bindFakeMailClient();
    $user = User::factory()->create();
    $account = imapAccountFor($user);

    $fake->seedMessages($account, 'INBOX', [
        new MailMessageDTO(
            uid: '1', subject: 'Preventivo', fromAddress: 'cliente@example.com', fromName: 'Cliente',
            toAddresses: ['me@example.com'], date: now()->toImmutable(), messageId: '<abc@example.com>',
            textBody: 'Vorrei un preventivo.', htmlBody: null, attachments: [],
        ),
    ]);

    $response = $this->actingAs($user)->getJson(route('mail.messages.forward', $account).'?folder=INBOX&uid=1');

    $response->assertOk();
    expect($response->json('to'))->toBe([]);
    expect($response->json('subject'))->toBe('Fwd: Preventivo');
    expect($response->json('in_reply_to'))->toBeNull();
});

test('send delivers a new message through the account\'s SMTP sender', function () {
    Mail::fake();
    $user = User::factory()->create();
    $account = imapAccountFor($user);

    $response = $this->actingAs($user)->postJson(route('mail.send'), [
        'mail_account_id' => $account->id,
        'to' => ['destinatario@example.com'],
        'subject' => 'Ciao',
        'body_html' => '<p>Corpo</p>',
    ]);

    $response->assertOk();
    Mail::assertSent(ComposedMail::class);
});

test('send accepts several attachments at once', function () {
    Mail::fake();
    $user = User::factory()->create();
    $account = imapAccountFor($user);

    $response = $this->actingAs($user)->postJson(route('mail.send'), [
        'mail_account_id' => $account->id,
        'to' => ['destinatario@example.com'],
        'subject' => 'Ciao',
        'body_html' => '<p>Corpo</p>',
        'attachments' => [
            UploadedFile::fake()->create('uno.pdf', 10),
            UploadedFile::fake()->create('due.pdf', 10),
        ],
    ]);

    $response->assertOk();
    Mail::assertSent(ComposedMail::class, function (ComposedMail $mail) {
        return count($mail->attachments()) === 2;
    });
});

test('send is refused for a mail account belonging to another user', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $account = imapAccountFor($owner);

    $response = $this->actingAs($intruder)->postJson(route('mail.send'), [
        'mail_account_id' => $account->id,
        'to' => ['destinatario@example.com'],
        'subject' => 'Ciao',
        'body_html' => '<p>Corpo</p>',
    ]);

    $response->assertForbidden();
    Mail::assertNothingSent();
});
