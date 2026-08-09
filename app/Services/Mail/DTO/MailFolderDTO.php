<?php

namespace App\Services\Mail\DTO;

/**
 * One entry in a mailbox's folder tree — path is the protocol's own
 * identifier (IMAP's dotted/slashed mailbox path, or the literal
 * "INBOX" pseudo-folder POP3 accounts always report, see
 * MailAccountProtocol::hasFolders()). parentPath, when not null, must
 * match another MailFolderDTO's own path within the same
 * listFolders() result — MailController::folders() normalizes any
 * parentPath that doesn't resolve within the returned set back to
 * null, so a reader implementation never has to worry about reporting
 * a dangling reference (e.g. Exchange's own root folder, which is
 * never itself returned as a row).
 */
final readonly class MailFolderDTO
{
    public function __construct(
        public string $path,
        public string $name,
        public bool $hasChildren = false,
        public ?string $parentPath = null,
    ) {}
}
