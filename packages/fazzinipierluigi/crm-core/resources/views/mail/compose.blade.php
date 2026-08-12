@extends('layouts.base')

@section('title', t('Nuovo messaggio'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('mail.index') }}">{{ t('E-mail') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('mail.compose') }}">{{ t('Nuovo messaggio') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="mail-compose-form" class="btn btn-primary" data-testid="mail-compose-submit">{{ t('Invia') }}</button>
@endsection

@section('content')
    <div
        id="mail-compose-app"
        data-testid="mail-compose-app"
        data-accounts="{{ json_encode($accounts->map(fn ($account) => [
            'id' => $account->id,
            'signature_html' => $account->renderedSignatureHtml(auth()->user()),
        ])->values()) }}"
        data-reply-url-base="{{ route('mail.messages.reply', 0) }}"
        data-forward-url-base="{{ route('mail.messages.forward', 0) }}"
        data-send-url="{{ route('mail.send') }}"
        data-index-url="{{ route('mail.index') }}"
        data-i18n="{{ json_encode([
            'sending' => t('Invio in corso…'),
            'send' => t('Invia'),
            'sendError' => t('Invio non riuscito. Riprova.'),
            'addressPlaceholder' => t('Scrivi un indirizzo e premi virgola o invio…'),
            'invalidAddress' => t('Indirizzo e-mail non valido.'),
            'dropzoneHint' => t('Trascina qui i file oppure clicca per selezionarli'),
            'removeAttachment' => t('Rimuovi allegato'),
        ]) }}"
    >
        <div id="mail-compose-error" class="alert alert-danger d-none" data-testid="mail-compose-error"></div>

        <div class="card">
            <div class="card-body">
                <form id="mail-compose-form" data-testid="mail-compose-form">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="mail_account_id" class="form-label">{{ t('Da') }}</label>
                            <select id="mail_account_id" name="mail_account_id" class="form-select" data-testid="mail-compose-account">
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->email_address }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="to" class="form-label">{{ t('A') }}</label>
                        <select id="to" name="to[]" multiple class="form-control" data-tom-select-manual data-testid="mail-compose-to"></select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cc" class="form-label">{{ t('Cc') }}</label>
                            <select id="cc" name="cc[]" multiple class="form-control" data-tom-select-manual data-testid="mail-compose-cc"></select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="bcc" class="form-label">{{ t('Ccn') }}</label>
                            <select id="bcc" name="bcc[]" multiple class="form-control" data-tom-select-manual data-testid="mail-compose-bcc"></select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="subject" class="form-label">{{ t('Oggetto') }}</label>
                        <input type="text" id="subject" name="subject" class="form-control" data-testid="mail-compose-subject">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ t('Messaggio') }}</label>
                        <textarea id="mail-compose-body" name="body_html" data-testid="mail-compose-body"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ t('Allegati') }}</label>
                        <div id="mail-compose-dropzone" class="mail-compose-dropzone" data-testid="mail-compose-dropzone">
                            <input type="file" id="mail-compose-attachment-input" multiple class="d-none" data-testid="mail-compose-attachments">
                            {!! icon('upload') !!}
                            <span>{{ t('Trascina qui i file oppure clicca per selezionarli') }}</span>
                        </div>
                        <div id="mail-compose-attachment-list" class="list-group mt-2" data-testid="mail-compose-attachment-list"></div>
                    </div>

                    <input type="hidden" id="in_reply_to" name="in_reply_to">
                    <input type="hidden" id="references" name="references">
                </form>
            </div>
        </div>
    </div>

    <style>
        .mail-compose-dropzone {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            border: 2px dashed var(--tblr-border-color);
            border-radius: var(--tblr-border-radius);
            padding: 1.5rem 1rem;
            color: var(--tblr-secondary);
            cursor: pointer;
            text-align: center;
        }
        .mail-compose-dropzone:hover { border-color: var(--tblr-primary); color: var(--tblr-primary); }
        .mail-compose-dropzone.mail-compose-dropzone-active { border-color: var(--tblr-primary); background: var(--tblr-primary-lt); color: var(--tblr-primary); }
    </style>

    @vite('resources/js/mail.js', 'vendor/crm')
@endsection
