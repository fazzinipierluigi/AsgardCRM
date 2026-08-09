<?php

namespace App\Services\Mail\Pop3;

/**
 * A minimal hand-rolled MIME parser — no external dependency needed:
 * boundary splitting is plain string work, and quoted_printable_decode()/
 * base64_decode()/mb_decode_mimeheader() are built into PHP. Handles
 * the common shapes (single-part, multipart/alternative,
 * multipart/mixed with attachments, one level of nesting) — not a
 * full RFC 2045 implementation. This is the riskiest, least-tested
 * corner of the POP3 support (see the "E-mail" module plan); the
 * IMAP/Exchange paths never touch it.
 */
class Pop3MessageParser
{
    /**
     * @return array<string, string> lower-cased header name => decoded value (first occurrence wins)
     */
    public function parseHeaders(string $rawHeaders): array
    {
        $unfolded = preg_replace("/\r\n[ \t]+/", ' ', str_replace("\n", "\r\n", $rawHeaders)) ?? $rawHeaders;
        $headers = [];

        foreach (explode("\r\n", $unfolded) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name));

            if (! isset($headers[$name])) {
                $headers[$name] = $this->decodeHeaderValue(trim($value));
            }
        }

        return $headers;
    }

    private function decodeHeaderValue(string $value): string
    {
        $decoded = @mb_decode_mimeheader($value);

        return $decoded !== false && $decoded !== '' ? $decoded : $value;
    }

    /**
     * @return array{textBody: ?string, htmlBody: ?string, attachments: list<array{filename: string, mimeType: ?string, content: string}>}
     */
    public function parseBody(array $headers, string $rawBody): array
    {
        $contentType = $headers['content-type'] ?? 'text/plain';

        if (preg_match('/boundary="?([^";]+)"?/i', $contentType, $matches)) {
            return $this->parseMultipart($rawBody, $matches[1]);
        }

        $decoded = $this->decodeTransfer($rawBody, $headers['content-transfer-encoding'] ?? '7bit');

        return str_starts_with(strtolower($contentType), 'text/html')
            ? ['textBody' => null, 'htmlBody' => $decoded, 'attachments' => []]
            : ['textBody' => $decoded, 'htmlBody' => null, 'attachments' => []];
    }

    /**
     * @return array{textBody: ?string, htmlBody: ?string, attachments: list<array{filename: string, mimeType: ?string, content: string}>}
     */
    private function parseMultipart(string $rawBody, string $boundary): array
    {
        $parts = explode('--'.$boundary, $rawBody);
        $textBody = null;
        $htmlBody = null;
        $attachments = [];

        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");

            if ($part === '' || $part === '--' || str_starts_with($part, '--')) {
                continue;
            }

            $split = preg_split("/\r?\n\r?\n/", $part, 2);

            if (count($split) !== 2) {
                continue;
            }

            [$partHeadersRaw, $partBody] = $split;
            $partHeaders = $this->parseHeaders($partHeadersRaw);
            $partContentType = $partHeaders['content-type'] ?? 'text/plain';

            if (preg_match('/boundary="?([^";]+)"?/i', $partContentType, $nested)) {
                $nestedResult = $this->parseMultipart($partBody, $nested[1]);
                $textBody ??= $nestedResult['textBody'];
                $htmlBody ??= $nestedResult['htmlBody'];
                $attachments = array_merge($attachments, $nestedResult['attachments']);

                continue;
            }

            $disposition = $partHeaders['content-disposition'] ?? '';
            $filename = $this->extractFilename($disposition) ?? $this->extractFilename($partContentType);
            $decoded = $this->decodeTransfer($partBody, $partHeaders['content-transfer-encoding'] ?? '7bit');

            if ($filename !== null || str_starts_with(strtolower($disposition), 'attachment')) {
                $attachments[] = [
                    'filename' => $filename ?? 'allegato',
                    'mimeType' => $this->baseMimeType($partContentType),
                    'content' => $decoded,
                ];

                continue;
            }

            if (str_starts_with(strtolower($partContentType), 'text/html')) {
                $htmlBody ??= $decoded;
            } elseif (str_starts_with(strtolower($partContentType), 'text/plain')) {
                $textBody ??= $decoded;
            }
        }

        return ['textBody' => $textBody, 'htmlBody' => $htmlBody, 'attachments' => $attachments];
    }

    private function decodeTransfer(string $content, string $encoding): string
    {
        return match (strtolower(trim($encoding))) {
            'base64' => (string) base64_decode(str_replace(["\r", "\n"], '', $content), true),
            'quoted-printable' => quoted_printable_decode($content),
            default => $content,
        };
    }

    private function extractFilename(string $header): ?string
    {
        if (preg_match('/(?:filename|name)="?([^";]+)"?/i', $header, $matches)) {
            return $this->decodeHeaderValue(trim($matches[1]));
        }

        return null;
    }

    private function baseMimeType(string $contentType): string
    {
        return trim(explode(';', $contentType)[0]);
    }
}
