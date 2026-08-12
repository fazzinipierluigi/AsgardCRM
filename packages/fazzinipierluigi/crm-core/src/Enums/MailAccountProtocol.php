<?php

namespace Fazzinipierluigi\CrmCore\Enums;

enum MailAccountProtocol: string
{
    case Imap = 'imap';
    case Pop3 = 'pop3';
    case Exchange = 'exchange';

    public function label(): string
    {
        return match ($this) {
            self::Imap => 'IMAP',
            self::Pop3 => 'POP3',
            self::Exchange => 'Exchange',
        };
    }

    /**
     * POP3 has no server-side folder concept — only one implicit
     * mailbox — so the webmail UI's folder tree degrades to a single
     * synthetic "Posta in arrivo" entry for these accounts instead of
     * calling MailReaderInterface::listFolders() for real options.
     */
    public function hasFolders(): bool
    {
        return $this !== self::Pop3;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
