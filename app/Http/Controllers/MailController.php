<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMailRequest;
use Fazzinipierluigi\CrmCore\Models\Entity;
use Fazzinipierluigi\CrmCore\Models\EntityRecord;
use App\Models\MailAccount;
use App\Models\MailSetting;
use App\Services\Mail\DTO\MailComposeAttachmentDTO;
use App\Services\Mail\DTO\MailComposeDTO;
use App\Services\Mail\DTO\MailFolderDTO;
use App\Services\Mail\DTO\MailMessageDTO;
use App\Services\Mail\DTO\MailMessageSummaryDTO;
use App\Services\Mail\MailClientFactory;
use App\Services\Mail\MailMessageHeaderCache;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The webmail UI — folder listings, message bodies and attachments are
 * all fetched live from the mail server on every call (see
 * MailReaderInterface's own docblock). The one exception is a folder's
 * first page of message headers, which MailMessageHeaderCache persists
 * to the database precisely so opening a folder doesn't have to wait
 * on a live round trip every time — see messages() and that class's
 * own docblock for the stale-while-revalidate shape. attach() is the
 * only other write path: it creates a single bookmark row on the
 * "E-mail" entity's dynamic table (mail_account_id + folder +
 * message_uid, see EntitySchemaBuilder's is_email branch) and then
 * hands off to the existing, unmodified EntityRelationLinkController
 * to create the actual N:M link — no new relation plumbing here.
 *
 * Authorization is split: browsing/reading a user's own mailbox needs
 * no ACL permission at all (ownership of the MailAccount is enough,
 * same reasoning as MailAccountController — it's a personal tool, not
 * a CRM record view). Only attach(), which creates a CRM-visible
 * entity_email record, is gated on entity_email.create — same
 * authorizeAction() shape as DocumentController/CalendarController.
 */
class MailController extends Controller
{
    private const MESSAGES_PER_PAGE = 25;

    public function __construct(
        private readonly MailClientFactory $clientFactory,
        private readonly MailMessageHeaderCache $headerCache,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $accounts = MailAccount::where('user_id', $request->user()->id)->where('is_active', true)->orderBy('name')->get();

        if ($accounts->isEmpty()) {
            return redirect()->route('mail.accounts.create');
        }

        return view('mail.index', ['accounts' => $accounts]);
    }

    public function folders(Request $request, MailAccount $mailAccount): JsonResponse
    {
        $this->authorizeOwnership($request, $mailAccount);

        if (! $mailAccount->protocol->hasFolders()) {
            return response()->json(['folders' => [['path' => 'INBOX', 'name' => 'Posta in arrivo', 'hasChildren' => false, 'parentPath' => null]]]);
        }

        return $this->withReaderErrorHandling(function () use ($mailAccount) {
            $folders = $this->clientFactory->readerFor($mailAccount)->listFolders($mailAccount);

            return response()->json(['folders' => $this->folderTreePayload($folders)]);
        });
    }

    public function messages(Request $request, MailAccount $mailAccount): JsonResponse
    {
        $this->authorizeOwnership($request, $mailAccount);

        $validated = $request->validate([
            'folder' => ['required', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        $folder = $validated['folder'];
        $page = (int) ($validated['page'] ?? 1);
        $forceRefresh = (bool) ($validated['refresh'] ?? false);

        // Only the folder's first page is cached at all (see
        // MailMessageHeaderCache's own docblock) — page 2+ always goes
        // straight to the live reader, same as before this cache
        // existed.
        if ($page === 1 && ! $forceRefresh) {
            $cached = $this->headerCache->cachedPage($mailAccount, $folder);

            if ($cached !== null) {
                return response()->json([
                    'items' => array_map($this->messageSummaryPayload(...), $cached['items']),
                    'total' => $cached['total'],
                    'cached' => true,
                ]);
            }
        }

        // Page 2+ never touches MailMessageHeaderCache — it keeps the
        // original short, ephemeral cache instead (anti-double-click,
        // never a bulk sync — see MailSetting's own docblock), since
        // persisting every page a user has ever paginated into isn't
        // worth the storage for pages that are opened far less often
        // than a folder's first one.
        $ttl = MailSetting::current()->cache_ttl_seconds;
        $fetch = fn () => $this->clientFactory->readerFor($mailAccount)->listMessages($mailAccount, $folder, $page, self::MESSAGES_PER_PAGE);

        return $this->withReaderErrorHandling(function () use ($mailAccount, $folder, $page, $ttl, $fetch) {
            $result = $page > 1 && $ttl > 0
                ? Cache::remember("mail-account-{$mailAccount->id}-messages-".md5("{$folder}-{$page}"), $ttl, $fetch)
                : $fetch();

            if ($page === 1) {
                $this->headerCache->store($mailAccount, $folder, $result['items'], $result['total']);
            }

            return response()->json([
                'items' => array_map($this->messageSummaryPayload(...), $result['items']),
                'total' => $result['total'],
                'cached' => false,
            ]);
        });
    }

    public function show(Request $request, MailAccount $mailAccount): JsonResponse
    {
        $this->authorizeOwnership($request, $mailAccount);

        $validated = $request->validate([
            'folder' => ['required', 'string'],
            'uid' => ['required', 'string'],
        ]);

        return $this->withReaderErrorHandling(function () use ($mailAccount, $validated) {
            $message = $this->clientFactory->readerFor($mailAccount)->fetchMessage($mailAccount, $validated['folder'], $validated['uid']);

            return response()->json([
                'uid' => $message->uid,
                'subject' => $message->subject,
                'from_address' => $message->fromAddress,
                'from_name' => $message->fromName,
                'to_addresses' => $message->toAddresses,
                'date' => $message->date?->toIso8601String(),
                'text_body' => $message->textBody,
                'html_body' => $message->htmlBody,
                'attachments' => array_map(fn ($attachment) => [
                    'id' => $attachment->id,
                    'filename' => $attachment->filename,
                    'mime_type' => $attachment->mimeType,
                    'size_bytes' => $attachment->sizeBytes,
                ], $message->attachments),
            ]);
        });
    }

    public function attachmentDownload(Request $request, MailAccount $mailAccount): Response
    {
        $this->authorizeOwnership($request, $mailAccount);

        $validated = $request->validate([
            'folder' => ['required', 'string'],
            'uid' => ['required', 'string'],
            'attachment_id' => ['required', 'string'],
        ]);

        $attachment = $this->withReaderErrorHandling(fn () => $this->clientFactory->readerFor($mailAccount)->fetchAttachment(
            $mailAccount, $validated['folder'], $validated['uid'], $validated['attachment_id']
        ));

        $maxBytes = MailSetting::current()->max_attachment_size_kb * 1024;

        abort_if($attachment->sizeBytes > $maxBytes, 413, 'Allegato troppo grande per essere scaricato.');

        return response($attachment->contents, 200, [
            'Content-Type' => $attachment->mimeType ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.addslashes($attachment->filename).'"',
            'Content-Length' => (string) $attachment->sizeBytes,
        ]);
    }

    public function compose(Request $request): View
    {
        $accounts = MailAccount::where('user_id', $request->user()->id)->where('is_active', true)->with('mailSignature')->orderBy('name')->get();

        abort_if($accounts->isEmpty(), 404);

        return view('mail.compose', ['accounts' => $accounts]);
    }

    /**
     * Prefill data for a reply — quotes the original message and
     * threads via In-Reply-To/References, same convention every mail
     * client uses. JSON, not a page: the compose view fetches this and
     * populates its own form fields client-side.
     */
    public function reply(Request $request, MailAccount $mailAccount): JsonResponse
    {
        $this->authorizeOwnership($request, $mailAccount);

        $validated = $request->validate(['folder' => ['required', 'string'], 'uid' => ['required', 'string']]);
        $message = $this->withReaderErrorHandling(fn () => $this->clientFactory->readerFor($mailAccount)->fetchMessage($mailAccount, $validated['folder'], $validated['uid']));

        return response()->json([
            'to' => array_values(array_filter([$message->fromAddress])),
            'subject' => str_starts_with(strtolower($message->subject), 're:') ? $message->subject : "Re: {$message->subject}",
            'body_html' => $this->quotedBody($message),
            'in_reply_to' => $message->messageId,
            'references' => $message->messageId,
        ]);
    }

    /**
     * Prefill data for a forward — quotes the original message, no
     * recipient prefilled, not threaded (no In-Reply-To — a forward
     * starts a new conversation for the recipient).
     */
    public function forward(Request $request, MailAccount $mailAccount): JsonResponse
    {
        $this->authorizeOwnership($request, $mailAccount);

        $validated = $request->validate(['folder' => ['required', 'string'], 'uid' => ['required', 'string']]);
        $message = $this->withReaderErrorHandling(fn () => $this->clientFactory->readerFor($mailAccount)->fetchMessage($mailAccount, $validated['folder'], $validated['uid']));

        return response()->json([
            'to' => [],
            'subject' => str_starts_with(strtolower($message->subject), 'fwd:') ? $message->subject : "Fwd: {$message->subject}",
            'body_html' => $this->quotedBody($message),
            'in_reply_to' => null,
            'references' => null,
        ]);
    }

    public function send(SendMailRequest $request): JsonResponse
    {
        $mailAccount = MailAccount::findOrFail($request->validated('mail_account_id'));
        $this->authorizeOwnership($request, $mailAccount);

        $attachments = array_map(fn ($file) => new MailComposeAttachmentDTO(
            filename: $file->getClientOriginalName(),
            mimeType: $file->getMimeType(),
            contents: $file->get(),
        ), $request->file('attachments', []));

        $compose = new MailComposeDTO(
            to: $request->validated('to'),
            cc: $request->validated('cc', []),
            bcc: $request->validated('bcc', []),
            subject: $request->validated('subject'),
            bodyHtml: $request->validated('body_html'),
            attachments: $attachments,
            inReplyTo: $request->validated('in_reply_to'),
            references: $request->validated('references'),
        );

        $this->withReaderErrorHandling(fn () => $this->clientFactory->senderFor($mailAccount)->send($mailAccount, $compose));

        return response()->json(['sent' => true]);
    }

    private function quotedBody(MailMessageDTO $message): string
    {
        $original = $message->htmlBody ?? nl2br(e($message->textBody ?? ''));
        $from = e($message->fromName ?? $message->fromAddress ?? '');
        $date = $message->date?->format('d/m/Y H:i') ?? '';

        return '<br><br><blockquote style="border-left: 2px solid #ccc; padding-left: 1em; color: #666;">'
            ."<p>{$from} — {$date}:</p>{$original}</blockquote>";
    }

    /**
     * The only write path: re-fetches the message live (never trusts
     * client-supplied metadata), then firstOrCreate()s the bookmark row
     * so repeated attach attempts for the same message are idempotent
     * — the dynamic table has no DB-level unique constraint to lean on
     * (see EntitySchemaBuilder's is_email branch), so uniqueness is
     * enforced here instead.
     */
    public function attach(Request $request, MailAccount $mailAccount): JsonResponse
    {
        $this->authorizeOwnership($request, $mailAccount);
        $emailEntity = $this->emailEntity();
        $this->authorizeAction($emailEntity, 'create');

        $validated = $request->validate([
            'folder' => ['required', 'string'],
            'uid' => ['required', 'string'],
        ]);

        $message = $this->withReaderErrorHandling(fn () => $this->clientFactory->readerFor($mailAccount)->fetchMessage($mailAccount, $validated['folder'], $validated['uid']));

        $record = EntityRecord::forEntity($emailEntity)->newQuery()->firstOrCreate(
            [
                'mail_account_id' => $mailAccount->id,
                'folder' => $validated['folder'],
                'message_uid' => $message->uid,
            ],
            [
                'user_id' => $request->user()->id,
                'oggetto' => $message->subject,
                'mittente' => $message->fromAddress,
                'destinatari' => implode(', ', $message->toAddresses),
                'data_messaggio' => $message->date?->format('Y-m-d H:i:s'),
                'ha_allegati' => $message->attachments !== [],
                'message_id' => $message->messageId,
            ]
        );

        return response()->json(['record_id' => $record->id, 'entity_slug' => $emailEntity->slug]);
    }

    private function authorizeOwnership(Request $request, MailAccount $mailAccount): void
    {
        abort_if($mailAccount->user_id !== $request->user()->id, 403);
    }

    /**
     * Every MailReaderInterface/MailSenderInterface call can fail for
     * reasons entirely outside this app's control — wrong credentials,
     * a provider blocking plain-password IMAP login (e.g. Gmail
     * requiring an app-specific password), a timeout, a dropped
     * connection. Without this, such a failure surfaced as a bare 500
     * whose body mail.js couldn't reliably distinguish from "no
     * folders/messages", so the webmail UI just looked silently empty
     * instead of explaining what actually went wrong (see the
     * MailController class docblock's "never a bulk sync" note — there
     * is no cached last-known-good state to fall back to either).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withReaderErrorHandling(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            throw new HttpResponseException(response()->json(['error' => $e->getMessage()], 502));
        }
    }

    private function authorizeAction(Entity $entity, string $action): void
    {
        if (! $entity->is_installed) {
            throw new NotFoundHttpException;
        }

        if (request()->user()->cannot("entity_{$entity->slug}.{$action}")) {
            abort(403);
        }
    }

    private function emailEntity(): Entity
    {
        return Entity::where('slug', 'email')->firstOrFail();
    }

    /**
     * @return array{path: string, name: string, hasChildren: bool}
     */
    /**
     * @param  list<MailFolderDTO>  $folders
     * @return list<array{path: string, name: string, hasChildren: bool, parentPath: ?string}>
     */
    private function folderTreePayload(array $folders): array
    {
        // A reader's parentPath is only ever an opaque id it got back
        // from the mail server (a dotted IMAP path segment, an EWS/
        // Graph parent folder id) — nothing guarantees that id is also
        // one of the folders returned in this same call (Exchange's
        // own root folder, for instance, is a valid parent but never a
        // row itself), so anything dangling is normalized to a root
        // entry here rather than asking every reader to know about
        // every other reader's edge cases.
        $knownPaths = array_flip(array_map(fn (MailFolderDTO $folder) => $folder->path, $folders));

        return array_map(fn (MailFolderDTO $folder) => $this->folderPayload($folder, $knownPaths), $folders);
    }

    /**
     * @param  array<string, int>  $knownPaths
     */
    private function folderPayload(MailFolderDTO $folder, array $knownPaths): array
    {
        return [
            'path' => $folder->path,
            'name' => $folder->name,
            'hasChildren' => $folder->hasChildren,
            'parentPath' => $folder->parentPath !== null && isset($knownPaths[$folder->parentPath]) ? $folder->parentPath : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messageSummaryPayload(MailMessageSummaryDTO $message): array
    {
        return [
            'uid' => $message->uid,
            'subject' => $message->subject,
            'from_address' => $message->fromAddress,
            'from_name' => $message->fromName,
            'date' => $message->date?->toIso8601String(),
            'has_attachments' => $message->hasAttachments,
            'is_read' => $message->isRead,
        ];
    }
}
