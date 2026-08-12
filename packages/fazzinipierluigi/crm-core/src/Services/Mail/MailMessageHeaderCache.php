<?php

namespace Fazzinipierluigi\CrmCore\Services\Mail;

use Fazzinipierluigi\CrmCore\Models\MailAccount;
use Fazzinipierluigi\CrmCore\Models\MailFolderCacheSync;
use Fazzinipierluigi\CrmCore\Models\MailMessageCache;
use Fazzinipierluigi\CrmCore\Services\Mail\DTO\MailMessageSummaryDTO;
use Illuminate\Support\Facades\DB;

/**
 * Persists a folder's first page of message headers (MailMessageCache)
 * so the webmail UI can paint a message list instantly from the
 * database instead of always waiting on a live IMAP/EWS/Graph round
 * trip — see MailController::messages(). Deliberately page 1 only:
 * that's the page a user hits on every folder switch, which is the
 * slowness this exists to fix; page 2+ still goes straight to the
 * live reader, same as before this cache existed.
 *
 * Stale-while-revalidate, not TTL-gated: cachedPage() always returns
 * whatever is on disk, however old, and MailController's `refresh`
 * query flag is what mail.js fires immediately after painting it to
 * silently bring the cache back in sync — there is no "is this fresh
 * enough" check here, since showing slightly-stale headers for the
 * one round trip it takes to refresh is strictly better than blocking
 * the UI on a live fetch every single time.
 */
class MailMessageHeaderCache
{
    /**
     * @return ?array{items: list<MailMessageSummaryDTO>, total: int}
     */
    public function cachedPage(MailAccount $account, string $folder): ?array
    {
        $sync = MailFolderCacheSync::query()
            ->where('mail_account_id', $account->id)
            ->where('folder', $folder)
            ->first();

        if ($sync === null) {
            return null;
        }

        $items = MailMessageCache::query()
            ->where('mail_account_id', $account->id)
            ->where('folder', $folder)
            ->orderByDesc('message_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (MailMessageCache $row) => new MailMessageSummaryDTO(
                uid: $row->uid,
                subject: $row->subject ?? '',
                fromAddress: $row->from_address,
                fromName: $row->from_name,
                date: $row->message_date?->toImmutable(),
                hasAttachments: $row->has_attachments,
                isRead: $row->is_read,
            ))
            ->values()
            ->all();

        return ['items' => $items, 'total' => $sync->total];
    }

    /**
     * Replaces the folder's entire cached page in one transaction —
     * simpler and safer than diffing against what's already there, and
     * self-healing: a message deleted on the server since the last
     * sync simply isn't in $items and so quietly disappears from the
     * cache too.
     *
     * @param  list<MailMessageSummaryDTO>  $items
     */
    public function store(MailAccount $account, string $folder, array $items, int $total): void
    {
        DB::transaction(function () use ($account, $folder, $items, $total) {
            MailMessageCache::query()
                ->where('mail_account_id', $account->id)
                ->where('folder', $folder)
                ->delete();

            foreach ($items as $item) {
                MailMessageCache::create([
                    'mail_account_id' => $account->id,
                    'folder' => $folder,
                    'uid' => $item->uid,
                    'subject' => $item->subject,
                    'from_address' => $item->fromAddress,
                    'from_name' => $item->fromName,
                    'message_date' => $item->date,
                    'has_attachments' => $item->hasAttachments,
                    'is_read' => $item->isRead,
                ]);
            }

            MailFolderCacheSync::updateOrCreate(
                ['mail_account_id' => $account->id, 'folder' => $folder],
                ['total' => $total, 'synced_at' => now()],
            );
        });
    }
}
