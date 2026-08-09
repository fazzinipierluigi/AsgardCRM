<?php

use App\Models\MailAccount;
use App\Models\MailFolderCacheSync;
use App\Models\MailMessageCache;
use App\Models\User;
use App\Services\Mail\DTO\MailMessageSummaryDTO;
use App\Services\Mail\MailMessageHeaderCache;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function headerCacheAccount(): MailAccount
{
    return MailAccount::create([
        'user_id' => User::factory()->create()->id, 'protocol' => 'imap', 'name' => 'Lavoro', 'email_address' => 'x@example.com',
        'config' => ['host' => 'imap.example.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'x@example.com', 'password' => 'shh'],
    ]);
}

test('cachedPage returns null when the folder was never synced', function () {
    $account = headerCacheAccount();

    expect((new MailMessageHeaderCache)->cachedPage($account, 'INBOX'))->toBeNull();
});

test('store persists headers and cachedPage reads them back ordered newest first', function () {
    $account = headerCacheAccount();
    $cache = new MailMessageHeaderCache;

    $older = new MailMessageSummaryDTO('1', 'Vecchio', 'a@example.com', 'A', now()->subDay()->toImmutable(), false, true);
    $newer = new MailMessageSummaryDTO('2', 'Nuovo', 'b@example.com', 'B', now()->toImmutable(), true, false);

    $cache->store($account, 'INBOX', [$older, $newer], 2);

    $result = $cache->cachedPage($account, 'INBOX');

    expect($result['total'])->toBe(2);
    expect($result['items'])->toHaveCount(2);
    expect($result['items'][0]->uid)->toBe('2');
    expect($result['items'][1]->uid)->toBe('1');
    expect(MailFolderCacheSync::query()->where('mail_account_id', $account->id)->where('folder', 'INBOX')->value('total'))->toBe(2);
});

test('store replaces the previous page entirely, so a deleted message disappears from the cache too', function () {
    $account = headerCacheAccount();
    $cache = new MailMessageHeaderCache;

    $cache->store($account, 'INBOX', [
        new MailMessageSummaryDTO('1', 'Uno', 'a@example.com', 'A', now()->toImmutable(), false, true),
        new MailMessageSummaryDTO('2', 'Due', 'b@example.com', 'B', now()->toImmutable(), false, true),
    ], 2);

    // "2" was deleted on the server since the last sync — a fresh
    // store() must not leave it behind as a stale row.
    $cache->store($account, 'INBOX', [
        new MailMessageSummaryDTO('1', 'Uno', 'a@example.com', 'A', now()->toImmutable(), false, true),
    ], 1);

    $result = $cache->cachedPage($account, 'INBOX');

    expect($result['items'])->toHaveCount(1);
    expect($result['items'][0]->uid)->toBe('1');
    expect(MailMessageCache::query()->where('mail_account_id', $account->id)->count())->toBe(1);
});

test('different folders on the same account are cached independently', function () {
    $account = headerCacheAccount();
    $cache = new MailMessageHeaderCache;

    $cache->store($account, 'INBOX', [new MailMessageSummaryDTO('1', 'Inbox', null, null, null, false, true)], 1);
    $cache->store($account, 'INBOX.Archivio', [new MailMessageSummaryDTO('9', 'Archivio', null, null, null, false, true)], 1);

    expect((new MailMessageHeaderCache)->cachedPage($account, 'INBOX')['items'][0]->uid)->toBe('1');
    expect((new MailMessageHeaderCache)->cachedPage($account, 'INBOX.Archivio')['items'][0]->uid)->toBe('9');
});
