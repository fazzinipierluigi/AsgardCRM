<?php

use App\Models\MailAccount;
use App\Models\MailSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('admin.mail-signatures.index'))->assertRedirect(route('login'));
});

test('admin can view the mail signatures index', function () {
    $this->actingAs(adminUser())->get(route('admin.mail-signatures.index'))->assertOk();
});

test('admin can create a signature with placeholders', function () {
    $response = $this->actingAs(adminUser())->post(route('admin.mail-signatures.store'), [
        'name' => 'Commerciale',
        'body_html' => '<p>{{user.name}} — {{user.job_title}}</p>',
    ]);

    $response->assertRedirect(route('admin.mail-signatures.index'));
    $signature = MailSignature::where('name', 'Commerciale')->firstOrFail();
    expect($signature->body_html)->toBe('<p>{{user.name}} — {{user.job_title}}</p>');
});

test('name and body_html are required', function () {
    $response = $this->actingAs(adminUser())->post(route('admin.mail-signatures.store'), []);

    $response->assertSessionHasErrors(['name', 'body_html']);
});

test('admin can update a signature', function () {
    $signature = MailSignature::create(['name' => 'Vecchia', 'body_html' => 'A']);

    $response = $this->actingAs(adminUser())->put(route('admin.mail-signatures.update', $signature), [
        'name' => 'Nuova',
        'body_html' => 'B',
    ]);

    $response->assertRedirect(route('admin.mail-signatures.index'));
    $signature->refresh();
    expect($signature->name)->toBe('Nuova');
    expect($signature->body_html)->toBe('B');
});

test('admin can delete a signature', function () {
    $signature = MailSignature::create(['name' => 'Da eliminare', 'body_html' => 'A']);

    $this->actingAs(adminUser())->delete(route('admin.mail-signatures.destroy', $signature));

    expect(MailSignature::find($signature->id))->toBeNull();
});

test('deleting a signature nulls it out on any mail account that had it assigned', function () {
    $user = User::factory()->create();
    $signature = MailSignature::create(['name' => 'Assegnata', 'body_html' => 'A']);
    $account = MailAccount::create([
        'user_id' => $user->id, 'protocol' => 'imap', 'name' => 'Lavoro', 'email_address' => 'x@example.com',
        'mail_signature_id' => $signature->id,
        'config' => ['host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'x@example.com', 'password' => 'shh'],
    ]);

    $this->actingAs(adminUser())->delete(route('admin.mail-signatures.destroy', $signature));

    expect($account->fresh()->mail_signature_id)->toBeNull();
});
