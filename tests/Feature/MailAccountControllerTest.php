<?php

use Fazzinipierluigi\CrmCore\Models\MailAccount;
use Fazzinipierluigi\CrmCore\Models\MailConnector;
use Fazzinipierluigi\CrmCore\Models\MailSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('mail.accounts.index'))->assertRedirect(route('login'));
});

test('a user can view their own accounts index', function () {
    $this->actingAs(User::factory()->create())->get(route('mail.accounts.index'))->assertOk();
});

test('a user can create an imap account, config includes smtp fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('mail.accounts.store'), [
        'name' => 'Lavoro',
        'protocol' => 'imap',
        'auth_method' => 'password',
        'email_address' => 'lavoro@example.com',
        'is_active' => '1',
        'imap_host' => 'imap.example.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_username' => 'lavoro@example.com',
        'imap_password' => 'shh',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'starttls',
        'smtp_username' => 'lavoro@example.com',
        'smtp_password' => 'shh-smtp',
    ]);

    $response->assertRedirect(route('mail.accounts.index'));
    $account = MailAccount::where('user_id', $user->id)->firstOrFail();
    expect($account->protocol->value)->toBe('imap');
    expect($account->config['host'])->toBe('imap.example.com');
    expect($account->config['password'])->toBe('shh');
    expect($account->config['smtp_host'])->toBe('smtp.example.com');
    expect($account->config['smtp_password'])->toBe('shh-smtp');
});

test('an imap account requires host and smtp credentials', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('mail.accounts.store'), [
        'name' => 'Lavoro',
        'protocol' => 'imap',
        'email_address' => 'lavoro@example.com',
    ]);

    $response->assertSessionHasErrors(['imap_host', 'imap_username', 'imap_password', 'smtp_host', 'smtp_username', 'smtp_password']);
});

test('a user can create a pop3 account with a single pseudo folder protocol', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('mail.accounts.store'), [
        'name' => 'Vecchia casella',
        'protocol' => 'pop3',
        'auth_method' => 'password',
        'email_address' => 'vecchia@example.com',
        'pop3_host' => 'pop3.example.com',
        'pop3_port' => 995,
        'pop3_encryption' => 'ssl',
        'pop3_username' => 'vecchia@example.com',
        'pop3_password' => 'shh',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'starttls',
        'smtp_username' => 'vecchia@example.com',
        'smtp_password' => 'shh-smtp',
    ]);

    $response->assertRedirect(route('mail.accounts.index'));
    $account = MailAccount::where('user_id', $user->id)->firstOrFail();
    expect($account->protocol->hasFolders())->toBeFalse();
});

test('a user can create an exchange account using a shared mail connector, without a password', function () {
    $user = User::factory()->create();
    $connector = MailConnector::create(['type' => 'exchange_graph', 'name' => 'Aziendale', 'slug' => 'aziendale', 'is_active' => true]);

    $response = $this->actingAs($user)->post(route('mail.accounts.store'), [
        'name' => 'Aziendale',
        'protocol' => 'exchange',
        'auth_method' => 'password',
        'email_address' => 'me@example.com',
        'mail_connector_id' => $connector->id,
    ]);

    $response->assertRedirect(route('mail.accounts.index'));
    $account = MailAccount::where('user_id', $user->id)->firstOrFail();
    expect($account->usesSharedConnector())->toBeTrue();
    expect($account->config)->toBe([]);
});

test('a direct exchange account without a connector requires ews credentials', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('mail.accounts.store'), [
        'name' => 'Exchange diretto',
        'protocol' => 'exchange',
        'email_address' => 'me@example.com',
    ]);

    $response->assertSessionHasErrors(['exchange_ews_url', 'exchange_username', 'exchange_password']);
});

test('a user cannot edit another user\'s mail account', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $account = MailAccount::create([
        'user_id' => $owner->id, 'protocol' => 'imap', 'name' => 'Lavoro', 'email_address' => 'lavoro@example.com',
        'config' => ['host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'lavoro@example.com', 'password' => 'shh'],
    ]);

    $this->actingAs($intruder)->get(route('mail.accounts.edit', $account))->assertForbidden();
    $this->actingAs($intruder)->put(route('mail.accounts.update', $account), ['name' => 'Hacked', 'protocol' => 'imap', 'email_address' => 'x@example.com'])->assertForbidden();
    $this->actingAs($intruder)->delete(route('mail.accounts.destroy', $account))->assertForbidden();
});

test('the edit form prefills the account\'s real saved config, not just port/encryption defaults', function () {
    // Regression test: the form used to build its field defaults via
    // old(null, $account->config ?? []) — Laravel's old() ignores the
    // given default entirely when the key is null, always returning the
    // (empty, absent a validation-error redirect) flashed old-input
    // bucket instead. Every field silently read as unset; host/username
    // rendered blank, while port/encryption happened to *look* correct
    // only by coincidence (falling back to the fieldset's own hardcoded
    // default port, and to the first MailEncryption enum case). See
    // _form.blade.php's $config assignment.
    $user = User::factory()->create();
    $account = MailAccount::create([
        'user_id' => $user->id, 'protocol' => 'imap', 'name' => 'Lavoro', 'email_address' => 'lavoro@example.com',
        'config' => [
            'host' => 'imap.example.com', 'port' => 993, 'encryption' => 'tls', 'username' => 'lavoro@example.com', 'password' => 'shh',
            'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_encryption' => 'starttls', 'smtp_username' => 'lavoro@example.com', 'smtp_password' => 'smtp-shh',
        ],
    ]);

    $response = $this->actingAs($user)->get(route('mail.accounts.edit', $account));

    $response->assertOk()
        ->assertSee('value="imap.example.com"', false)
        ->assertSee('value="lavoro@example.com"', false)
        ->assertSee('value="smtp.example.com"', false)
        // encryption is a <select>, not an input value= — assert the
        // real saved option ("tls") is the one marked selected, not
        // "ssl" (the enum's first case, which the old bug always
        // defaulted to regardless of what was actually saved).
        ->assertSee('<option value="tls" selected', false)
        ->assertDontSee('<option value="ssl" selected', false);
});

test('blank password on update keeps the previously stored one', function () {
    $user = User::factory()->create();
    $account = MailAccount::create([
        'user_id' => $user->id, 'protocol' => 'imap', 'name' => 'Lavoro', 'email_address' => 'lavoro@example.com',
        'config' => [
            'host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'lavoro@example.com', 'password' => 'original-secret',
            'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_encryption' => 'starttls', 'smtp_username' => 'lavoro@example.com', 'smtp_password' => 'original-smtp-secret',
        ],
    ]);

    $response = $this->actingAs($user)->put(route('mail.accounts.update', $account), [
        'name' => 'Lavoro (rinominato)',
        'protocol' => 'imap',
        'auth_method' => 'password',
        'email_address' => 'lavoro@example.com',
        'imap_host' => 'imap.example.com',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_username' => 'lavoro@example.com',
        'imap_password' => '',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'starttls',
        'smtp_username' => 'lavoro@example.com',
        'smtp_password' => '',
    ]);

    $response->assertRedirect(route('mail.accounts.index'));
    $fresh = $account->fresh();
    expect($fresh->name)->toBe('Lavoro (rinominato)');
    expect($fresh->config['password'])->toBe('original-secret');
    expect($fresh->config['smtp_password'])->toBe('original-smtp-secret');
});

test('a user can assign and later unassign a signature to their account', function () {
    $user = User::factory()->create();
    $signature = MailSignature::create(['name' => 'Commerciale', 'body_html' => '<p>{{user.name}}</p>']);
    $account = MailAccount::create([
        'user_id' => $user->id, 'protocol' => 'imap', 'name' => 'Lavoro', 'email_address' => 'x@example.com',
        'config' => ['host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'x@example.com', 'password' => 'shh'],
    ]);

    $this->actingAs($user)->put(route('mail.accounts.update', $account), [
        'name' => 'Lavoro', 'protocol' => 'imap', 'auth_method' => 'password', 'email_address' => 'x@example.com',
        'mail_signature_id' => $signature->id,
        'imap_host' => 'imap.example.com', 'imap_port' => 993, 'imap_encryption' => 'ssl', 'imap_username' => 'x@example.com', 'imap_password' => 'shh',
        'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_encryption' => 'starttls', 'smtp_username' => 'x@example.com', 'smtp_password' => 'smtp-shh',
    ])->assertRedirect(route('mail.accounts.index'));

    expect($account->fresh()->mail_signature_id)->toBe($signature->id);

    $this->actingAs($user)->put(route('mail.accounts.update', $account), [
        'name' => 'Lavoro', 'protocol' => 'imap', 'auth_method' => 'password', 'email_address' => 'x@example.com',
        'mail_signature_id' => '',
        'imap_host' => 'imap.example.com', 'imap_port' => 993, 'imap_encryption' => 'ssl', 'imap_username' => 'x@example.com', 'imap_password' => 'shh',
        'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_encryption' => 'starttls', 'smtp_username' => 'x@example.com', 'smtp_password' => 'smtp-shh',
    ])->assertRedirect(route('mail.accounts.index'));

    expect($account->fresh()->mail_signature_id)->toBeNull();
});

test('a mail account can be deleted by its owner', function () {
    $user = User::factory()->create();
    $account = MailAccount::create([
        'user_id' => $user->id, 'protocol' => 'imap', 'name' => 'Da eliminare', 'email_address' => 'x@example.com',
        'config' => ['host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'x@example.com', 'password' => 'shh'],
    ]);

    $this->actingAs($user)->delete(route('mail.accounts.destroy', $account));

    expect(MailAccount::find($account->id))->toBeNull();
});
