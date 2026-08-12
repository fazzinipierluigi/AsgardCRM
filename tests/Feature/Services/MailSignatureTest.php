<?php

use Fazzinipierluigi\CrmCore\Models\MailSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('render replaces every recognized placeholder with the user\'s own field, HTML-escaped', function () {
    $signature = MailSignature::create([
        'name' => 'Commerciale',
        'body_html' => '<p>Cordiali saluti,<br>{{user.name}}<br>{{user.job_title}}<br>{{user.email}} — {{user.phone}}</p>',
    ]);
    $user = User::factory()->create([
        'name' => 'Mario <Rossi>', 'email' => 'mario@example.com', 'phone' => '+39 000', 'job_title' => 'Sales & Ops',
    ]);

    $html = $signature->render($user);

    expect($html)->toBe('<p>Cordiali saluti,<br>Mario &lt;Rossi&gt;<br>Sales &amp; Ops<br>mario@example.com — +39 000</p>');
});

test('render leaves an unrecognized placeholder untouched instead of dropping it', function () {
    $signature = MailSignature::create(['name' => 'Rotto', 'body_html' => '{{user.name}} — {{user.department}}']);
    $user = User::factory()->create(['name' => 'Anna']);

    expect($signature->render($user))->toBe('Anna — {{user.department}}');
});

test('render substitutes an empty string for a null phone/job_title', function () {
    $signature = MailSignature::create(['name' => 'Minimal', 'body_html' => '[{{user.phone}}][{{user.job_title}}]']);
    $user = User::factory()->create(['phone' => null, 'job_title' => null]);

    expect($signature->render($user))->toBe('[][]');
});
