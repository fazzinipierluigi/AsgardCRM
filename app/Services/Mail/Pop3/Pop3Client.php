<?php

namespace App\Services\Mail\Pop3;

use App\Enums\MailEncryption;
use RuntimeException;

/**
 * A minimal, hand-rolled POP3 client (RFC 1939) — no mature,
 * actively-maintained Laravel-friendly POP3 package exists, and the
 * protocol's command set is small enough that a raw socket
 * implementation is the pragmatic choice (see the "E-mail" module
 * plan). Deliberately covers only what Pop3MailReader needs: USER/
 * PASS, STAT, LIST, UIDL, TOP, RETR, DELE, QUIT.
 */
class Pop3Client
{
    /** @var resource|null */
    private $socket;

    public function connect(string $host, int $port, MailEncryption $encryption, int $timeoutSeconds): void
    {
        $transport = $encryption === MailEncryption::Ssl ? 'ssl' : 'tcp';
        $address = "{$transport}://{$host}:{$port}";

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($address, $errno, $errstr, $timeoutSeconds);

        if ($socket === false) {
            throw new RuntimeException("Impossibile connettersi a {$host}:{$port} ({$errstr}).");
        }

        stream_set_timeout($socket, $timeoutSeconds);
        $this->socket = $socket;
        $this->readLine(); // server greeting

        if ($encryption === MailEncryption::StartTls) {
            $this->command('STLS');

            if (! stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Impossibile avviare STARTTLS.');
            }
        }
    }

    public function login(string $username, string $password): void
    {
        $this->command('USER '.$username);
        $this->command('PASS '.$password);
    }

    /**
     * @return array{count: int, size: int}
     */
    public function stat(): array
    {
        $response = $this->command('STAT');
        [$count, $size] = array_pad(explode(' ', trim($response)), 2, '0');

        return ['count' => (int) $count, 'size' => (int) $size];
    }

    /**
     * @return array<int, int> message number => size in bytes
     */
    public function listMessages(): array
    {
        $this->command('LIST');

        $sizes = [];

        foreach ($this->readMultilineLines() as $line) {
            [$msgnum, $size] = array_pad(explode(' ', trim($line)), 2, '0');
            $sizes[(int) $msgnum] = (int) $size;
        }

        return $sizes;
    }

    /**
     * @return array<int, string> message number => stable unique id, empty if the server doesn't support UIDL
     */
    public function uidl(): array
    {
        try {
            $this->command('UIDL');
        } catch (RuntimeException) {
            return [];
        }

        $uids = [];

        foreach ($this->readMultilineLines() as $line) {
            [$msgnum, $uid] = array_pad(explode(' ', trim($line), 2), 2, '');
            $uids[(int) $msgnum] = $uid;
        }

        return $uids;
    }

    /**
     * Headers only (TOP <n> 0) — cheap way to build a message list
     * without downloading every full body via RETR.
     */
    public function retrieveHeaders(int $messageNumber): string
    {
        $this->command('TOP '.$messageNumber.' 0');

        return implode("\r\n", $this->readMultilineLines());
    }

    public function retrieve(int $messageNumber): string
    {
        $this->command('RETR '.$messageNumber);

        return implode("\r\n", $this->readMultilineLines());
    }

    public function delete(int $messageNumber): void
    {
        $this->command('DELE '.$messageNumber);
    }

    public function quit(): void
    {
        if ($this->socket !== null) {
            try {
                $this->command('QUIT');
            } catch (RuntimeException) {
                // Best effort — we're closing the socket regardless.
            }
        }

        $this->disconnect();
    }

    public function disconnect(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Sends a command and returns the single-line +OK response
     * (without the leading "+OK "), throwing on -ERR.
     */
    private function command(string $line): string
    {
        $this->write($line);

        return $this->readLine();
    }

    private function write(string $line): void
    {
        if ($this->socket === null) {
            throw new RuntimeException('Connessione POP3 non stabilita.');
        }

        fwrite($this->socket, $line."\r\n");
    }

    private function readLine(): string
    {
        if ($this->socket === null) {
            throw new RuntimeException('Connessione POP3 non stabilita.');
        }

        $line = fgets($this->socket, 1024);

        if ($line === false) {
            throw new RuntimeException('Connessione POP3 interrotta.');
        }

        $line = rtrim($line, "\r\n");

        if (str_starts_with($line, '-ERR')) {
            throw new RuntimeException('Errore POP3: '.trim(substr($line, 4)));
        }

        return str_starts_with($line, '+OK') ? trim(substr($line, 3)) : $line;
    }

    /**
     * Reads a dot-terminated multiline block (RFC 1939 §3): lines up to
     * a lone "." on its own line, byte-stuffed leading dots undone.
     *
     * @return list<string>
     */
    private function readMultilineLines(): array
    {
        if ($this->socket === null) {
            throw new RuntimeException('Connessione POP3 non stabilita.');
        }

        $lines = [];

        while (! feof($this->socket)) {
            $line = fgets($this->socket, 8192);

            if ($line === false) {
                break;
            }

            $line = rtrim($line, "\r\n");

            if ($line === '.') {
                break;
            }

            $lines[] = str_starts_with($line, '..') ? substr($line, 1) : $line;
        }

        return $lines;
    }
}
