@extends('layouts.base')

@section('title', t('E-mail'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('mail.index') }}">{{ t('E-mail') }}</a>
    </li>
@endsection

@section('buttons')
    <a href="{{ route('mail.accounts.index') }}" class="btn btn-outline-secondary" data-testid="mail-manage-accounts-link">
        {{ t('Gestisci caselle') }}
    </a>
    <a href="{{ route('mail.compose') }}" class="btn btn-primary" data-testid="mail-compose-link">
        {!! icon('pencil-plus') !!}
        {{ t('Nuovo messaggio') }}
    </a>
@endsection

@section('content')
    <div
        id="mail-app"
        data-testid="mail-app"
        data-accounts="{{ json_encode($accounts->map(fn ($account) => [
            'id' => $account->id,
            'name' => $account->name,
            'email' => $account->email_address,
            'has_folders' => $account->protocol->hasFolders(),
            'folders_url' => route('mail.folders', $account),
            'messages_url' => route('mail.messages', $account),
            'show_url' => route('mail.messages.show', $account),
            'attachment_url' => route('mail.messages.attachment', $account),
            'attach_url' => route('mail.attach', $account),
        ])->values()) }}"
        data-email-record-url-base="{{ route('entities.show', ['email', 0]) }}"
        data-compose-url="{{ route('mail.compose') }}"
        data-folder-icons="{{ json_encode([
            'inbox' => icon('inbox'),
            'sent' => icon('send'),
            'drafts' => icon('file-text'),
            'trash' => icon('trash'),
            'default' => icon('folder'),
            'attachment' => icon('paperclip'),
            'read' => icon('mail-opened'),
            'chevron' => icon('chevron-right'),
        ]) }}"
        data-i18n="{{ json_encode([
            'noMessages' => t('Nessun messaggio in questa cartella.'),
            'noMessageSelected' => t('Seleziona un messaggio per leggerlo.'),
            'loading' => t('Caricamento…'),
            'attachments' => t('Allegati'),
            'attachToRecord' => t('Allega a un record'),
            'attaching' => t('Collegamento…'),
            'loadError' => t('Impossibile contattare la casella. Riprova.'),
            'noAccounts' => t('Nessuna casella attiva.'),
            'reply' => t('Rispondi'),
            'forward' => t('Inoltra'),
            'noSubject' => t('(senza oggetto)'),
        ]) }}"
    >
        <div class="card">
            <div class="row g-0 flex-fill" style="min-height: 0;">
                <div class="col-12 col-md-3 col-xl-2 border-end d-flex flex-column">
                    <div class="p-2 border-bottom">
                        <select id="mail-account-select" class="form-select form-select-sm" data-tom-select-manual data-testid="mail-account-select"></select>
                    </div>
                    <div id="mail-folder-tree" class="mail-folder-tree p-2 flex-fill" data-testid="mail-folder-tree" style="min-height: 0; overflow-y: auto;"></div>
                </div>
                <div class="col-12 col-md-4 col-xl-3 border-end d-flex flex-column">
                    <div id="mail-message-list" class="list-group list-group-flush flex-fill" data-testid="mail-message-list" style="min-height: 0; overflow-y: auto;"></div>
                </div>
                <div class="col d-none d-md-flex flex-column">
                    <div id="mail-reading-pane" class="flex-fill" data-testid="mail-reading-pane" style="min-height: 0; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Tabler's own .page/.page-wrapper/.page-body chain never
           actually anchors to the viewport height by default (nothing
           upstream sets height/min-height in vh units — see
           tabler.css), so #mail-app's card used a flat 65vh instead,
           which left dead space on a tall window and clipped content
           on a short one. Scoped to this page only: turning that whole
           chain into one flex column anchored to 100vh lets #mail-app
           grow to fill exactly what's left below the header/breadcrumb
           instead of guessing a fraction of the viewport.
        */
        .page { min-height: 100vh; }
        .page-body,
        .container-fluid,
        #mail-app,
        #mail-app > .card {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
        }
        /* .row is already display:flex (Bootstrap's own grid), row
           direction — it only needs to grow within the column chain
           above, not be redirected. */
        #mail-app > .card > .row {
            flex: 1;
            min-height: 0;
        }

        .mail-folder-row { border-radius: var(--tblr-border-radius); padding: .125rem .25rem; }
        .mail-folder-row:hover { background: var(--tblr-bg-surface-secondary); }
        .mail-folder-toggle,
        .mail-folder-spacer { display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; flex-shrink: 0; }
        .mail-folder-toggle { background: none; border: 0; padding: 0; color: var(--tblr-secondary); cursor: pointer; }
        .mail-folder-toggle .icon { transition: transform .15s ease; transform: rotate(90deg); }
        .mail-folder-toggle.collapsed .icon { transform: rotate(0deg); }
        .mail-folder-link { padding: .25rem 0; border: 0; background: none; color: inherit; text-align: left; }
        .mail-folder-link.active { background: var(--tblr-primary-lt); border-radius: var(--tblr-border-radius); }
    </style>

    @vite('resources/js/mail.js', 'vendor/crm')
@endsection
