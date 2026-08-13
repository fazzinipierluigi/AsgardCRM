<?php

use Fazzinipierluigi\AsgardCRM\Services\Mail\Pop3\Pop3MessageParser;

test('parses simple headers, including folded lines', function () {
    $raw = "Subject: Ciao\r\nFrom: A <a@example.com>\r\nTo: b@example.com,\r\n c@example.com\r\n";

    $headers = (new Pop3MessageParser)->parseHeaders($raw);

    expect($headers['subject'])->toBe('Ciao');
    expect($headers['from'])->toBe('A <a@example.com>');
    expect($headers['to'])->toBe('b@example.com, c@example.com');
});

test('decodes a MIME-encoded subject', function () {
    $raw = "Subject: =?UTF-8?B?Q2lhbyBtb25kbyE=?=\r\n";

    $headers = (new Pop3MessageParser)->parseHeaders($raw);

    expect($headers['subject'])->toBe('Ciao mondo!');
});

test('parses a plain text/plain body', function () {
    $headers = ['content-type' => 'text/plain; charset=utf-8'];

    $parsed = (new Pop3MessageParser)->parseBody($headers, 'Ciao a tutti.');

    expect($parsed['textBody'])->toBe('Ciao a tutti.');
    expect($parsed['htmlBody'])->toBeNull();
    expect($parsed['attachments'])->toBe([]);
});

test('parses a multipart/alternative body into text and html', function () {
    $boundary = 'BOUND123';
    $body = "--{$boundary}\r\nContent-Type: text/plain\r\n\r\nVersione testo.\r\n"
        ."--{$boundary}\r\nContent-Type: text/html\r\n\r\n<p>Versione html.</p>\r\n"
        ."--{$boundary}--\r\n";

    $parsed = (new Pop3MessageParser)->parseBody(['content-type' => "multipart/alternative; boundary=\"{$boundary}\""], $body);

    expect(trim($parsed['textBody']))->toBe('Versione testo.');
    expect(trim($parsed['htmlBody']))->toBe('<p>Versione html.</p>');
});

test('extracts a base64-encoded attachment from a multipart/mixed body', function () {
    $boundary = 'BOUND456';
    $encoded = base64_encode('contenuto del file');
    $body = "--{$boundary}\r\nContent-Type: text/plain\r\n\r\nCorpo.\r\n"
        ."--{$boundary}\r\nContent-Type: application/pdf; name=\"documento.pdf\"\r\nContent-Disposition: attachment; filename=\"documento.pdf\"\r\nContent-Transfer-Encoding: base64\r\n\r\n{$encoded}\r\n"
        ."--{$boundary}--\r\n";

    $parsed = (new Pop3MessageParser)->parseBody(['content-type' => "multipart/mixed; boundary=\"{$boundary}\""], $body);

    expect(trim($parsed['textBody']))->toBe('Corpo.');
    expect($parsed['attachments'])->toHaveCount(1);
    expect($parsed['attachments'][0]['filename'])->toBe('documento.pdf');
    expect($parsed['attachments'][0]['mimeType'])->toBe('application/pdf');
    expect($parsed['attachments'][0]['content'])->toBe('contenuto del file');
});

test('decodes quoted-printable content', function () {
    $headers = ['content-type' => 'text/plain', 'content-transfer-encoding' => 'quoted-printable'];

    $parsed = (new Pop3MessageParser)->parseBody($headers, 'Caff=C3=A8 e cornetto');

    expect($parsed['textBody'])->toBe('Caffè e cornetto');
});
