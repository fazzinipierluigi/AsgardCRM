<?php

use Fazzinipierluigi\CrmCore\Mail\ComposedMail;
use Fazzinipierluigi\CrmCore\Models\MailAccount;
use App\Models\User;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailComposeAttachmentDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailComposeDTO;
use Fazzinipierluigi\CrmCore\Services\Mail\SmtpMailSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function smtpAccount(): MailAccount
{
    return MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'imap', 'name' => 'Lavoro', 'email_address' => 'me@example.com',
        'config' => [
            'host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'me@example.com', 'password' => 'shh',
            'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_encryption' => 'starttls', 'smtp_username' => 'me@example.com', 'smtp_password' => 'smtp-shh',
        ],
    ]);
}

test('send registers a per-account ephemeral mailer and sends via it', function () {
    Mail::fake();
    $account = smtpAccount();

    $compose = new MailComposeDTO(
        to: ['destinatario@example.com'],
        cc: [],
        bcc: [],
        subject: 'Ciao',
        bodyHtml: '<p>Corpo del messaggio</p>',
        attachments: [],
    );

    (new SmtpMailSender)->send($account, $compose);

    Mail::assertSent(ComposedMail::class, function (ComposedMail $mail) {
        return $mail->envelope()->subject === 'Ciao'
            && $mail->envelope()->to[0]->address === 'destinatario@example.com'
            && $mail->envelope()->from->address === 'me@example.com';
    });
    expect(config("mail.mailers.mail-account-{$account->id}.host"))->toBe('smtp.example.com');
    expect(config("mail.mailers.mail-account-{$account->id}.encryption"))->toBe('starttls');
});

test('an oauth account sends through the provider\'s smtp host using the access token as password', function () {
    Mail::fake();
    $account = MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'imap', 'auth_method' => 'google_oauth', 'name' => 'Gmail', 'email_address' => 'me@gmail.com',
        'config' => ['oauth_provider' => 'google', 'access_token' => 'fresh-token', 'refresh_token' => 'refresh-me', 'token_expires_at' => now()->addHour()->toIso8601String()],
    ]);

    $compose = new MailComposeDTO(
        to: ['destinatario@example.com'],
        cc: [],
        bcc: [],
        subject: 'Ciao',
        bodyHtml: '<p>Corpo</p>',
        attachments: [],
    );

    (new SmtpMailSender)->send($account, $compose);

    expect(config("mail.mailers.mail-account-{$account->id}.host"))->toBe('smtp.gmail.com');
    expect(config("mail.mailers.mail-account-{$account->id}.port"))->toBe(465);
    expect(config("mail.mailers.mail-account-{$account->id}.password"))->toBe('fresh-token');
});

test('send includes attachments', function () {
    Mail::fake();
    $account = smtpAccount();

    $compose = new MailComposeDTO(
        to: ['destinatario@example.com'],
        cc: [],
        bcc: [],
        subject: 'Con allegato',
        bodyHtml: '<p>Vedi allegato</p>',
        attachments: [new MailComposeAttachmentDTO('nota.txt', 'text/plain', 'contenuto')],
    );

    (new SmtpMailSender)->send($account, $compose);

    Mail::assertSent(ComposedMail::class, function (ComposedMail $mail) {
        return count($mail->attachments()) === 1 && $mail->attachments()[0]->as === 'nota.txt';
    });
});

test('send threads a reply via In-Reply-To/References headers', function () {
    Mail::fake();
    $account = smtpAccount();

    $compose = new MailComposeDTO(
        to: ['destinatario@example.com'],
        cc: [],
        bcc: [],
        subject: 'Re: Ciao',
        bodyHtml: '<p>Risposta</p>',
        attachments: [],
        inReplyTo: '<abc@example.com>',
        references: '<abc@example.com>',
    );

    (new SmtpMailSender)->send($account, $compose);

    Mail::assertSent(ComposedMail::class, function (ComposedMail $mail) {
        $headers = $mail->headers();

        return ($headers->text['In-Reply-To'] ?? null) === '<abc@example.com>'
            && in_array('<abc@example.com>', $headers->references, true);
    });
});
