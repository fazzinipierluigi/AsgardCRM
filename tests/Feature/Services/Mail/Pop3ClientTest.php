<?php

use Fazzinipierluigi\CrmCore\Enums\MailEncryption;
use Fazzinipierluigi\CrmCore\Services\Mail\Pop3\Pop3Client;

/**
 * Drives Pop3Client against a real, in-process POP3 stub server
 * (127.0.0.1 loopback, forked via pcntl so it can run its own blocking
 * accept/respond loop alongside the client in the same test process)
 * instead of mocking the socket — the one part of the mail module
 * simple and dependency-free enough to get genuine wire-protocol
 * coverage, per the "E-mail" module plan. Skipped outside pcntl-capable
 * environments (e.g. some CI images ship PHP without pcntl).
 */
function startFakePop3Server(array $script): array
{
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

    if ($server === false) {
        test()->fail("Could not start fake POP3 server: {$errstr}");
    }

    $address = stream_socket_get_name($server, false);
    $port = (int) substr($address, strrpos($address, ':') + 1);

    $pid = pcntl_fork();

    if ($pid === -1) {
        test()->fail('pcntl_fork failed.');
    }

    if ($pid === 0) {
        // Child: serve exactly one connection, then exit without ever
        // touching Laravel/DB state (avoids double-running test teardown).
        $conn = stream_socket_accept($server, 10);

        if ($conn !== false) {
            fwrite($conn, "+OK fake pop3 ready\r\n");

            foreach ($script as $expectedPrefix => $response) {
                $line = fgets($conn, 1024);

                if ($line === false || ! str_starts_with($line, $expectedPrefix)) {
                    fwrite($conn, "-ERR unexpected command\r\n");

                    continue;
                }

                fwrite($conn, $response);
            }

            fclose($conn);
        }

        fclose($server);
        exit(0);
    }

    fclose($server);

    return ['pid' => $pid, 'port' => $port];
}

function stopFakePop3Server(int $pid): void
{
    pcntl_waitpid($pid, $status);
}

test('login, stat, uidl, retrieve and quit against a real loopback socket', function () {
    if (! function_exists('pcntl_fork')) {
        test()->markTestSkipped('pcntl extension not available.');
    }

    $rawMessage = "Subject: Ciao\r\nFrom: a@example.com\r\n\r\nCorpo del messaggio.";

    ['pid' => $pid, 'port' => $port] = startFakePop3Server([
        'USER' => "+OK\r\n",
        'PASS' => "+OK logged in\r\n",
        'STAT' => "+OK 1 120\r\n",
        'UIDL' => "+OK\r\n1 uid-abc-123\r\n.\r\n",
        'RETR' => "+OK 120 octets\r\n{$rawMessage}\r\n.\r\n",
        'QUIT' => "+OK bye\r\n",
    ]);

    try {
        $client = new Pop3Client;
        $client->connect('127.0.0.1', $port, MailEncryption::None, 5);
        $client->login('me@example.com', 'secret');

        expect($client->stat())->toBe(['count' => 1, 'size' => 120]);
        expect($client->uidl())->toBe([1 => 'uid-abc-123']);
        expect($client->retrieve(1))->toBe($rawMessage);

        $client->quit();
    } finally {
        stopFakePop3Server($pid);
    }
});

test('a -ERR response during login throws', function () {
    if (! function_exists('pcntl_fork')) {
        test()->markTestSkipped('pcntl extension not available.');
    }

    ['pid' => $pid, 'port' => $port] = startFakePop3Server([
        'USER' => "+OK\r\n",
        'PASS' => "-ERR invalid password\r\n",
    ]);

    try {
        $client = new Pop3Client;
        $client->connect('127.0.0.1', $port, MailEncryption::None, 5);

        expect(fn () => $client->login('me@example.com', 'wrong'))->toThrow(RuntimeException::class);
    } finally {
        stopFakePop3Server($pid);
    }
});
