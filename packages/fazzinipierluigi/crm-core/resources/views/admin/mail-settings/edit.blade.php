@extends('layouts.admin')

@section('title', t('Impostazioni e-mail'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">
        <a href="{{ route('admin.mail-settings.edit') }}">{{ t('Impostazioni e-mail') }}</a>
    </li>
@endsection

@section('buttons')
    <button type="submit" form="mail-settings-form" class="btn btn-primary" data-testid="mail-settings-submit">{{ t('Salva impostazioni') }}</button>
@endsection

@section('content')
    @if (session('status') === 'mail-settings-updated')
        <div class="alert alert-success" data-testid="mail-settings-updated">{{ t('Impostazioni e-mail aggiornate.') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.mail-settings.update') }}" method="POST" id="mail-settings-form">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="connection_timeout_seconds" class="form-label">{{ t('Timeout connessione (secondi)') }}</label>
                        <input type="number" min="1" max="120" id="connection_timeout_seconds" name="connection_timeout_seconds" value="{{ old('connection_timeout_seconds', $setting->connection_timeout_seconds) }}" class="form-control @error('connection_timeout_seconds') is-invalid @enderror">
                        @error('connection_timeout_seconds') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="max_attachment_size_kb" class="form-label">{{ t('Dimensione massima allegato (KB)') }}</label>
                        <input type="number" min="1" id="max_attachment_size_kb" name="max_attachment_size_kb" value="{{ old('max_attachment_size_kb', $setting->max_attachment_size_kb) }}" class="form-control @error('max_attachment_size_kb') is-invalid @enderror">
                        @error('max_attachment_size_kb') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="cache_ttl_seconds" class="form-label">{{ t('Cache elenchi (secondi)') }}</label>
                        <input type="number" min="0" max="3600" id="cache_ttl_seconds" name="cache_ttl_seconds" value="{{ old('cache_ttl_seconds', $setting->cache_ttl_seconds) }}" class="form-control @error('cache_ttl_seconds') is-invalid @enderror">
                        @error('cache_ttl_seconds') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="form-hint">{{ t('Solo per evitare richieste ripetute alla casella durante la navigazione: la posta non viene mai sincronizzata in blocco.') }}</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ t('Protocolli abilitati') }}</label>
                    @php $enabledProtocols = old('enabled_protocols', $setting->enabled_protocols ?? []); @endphp
                    @foreach (\Fazzinipierluigi\CrmCore\Enums\MailAccountProtocol::options() as $value => $label)
                        <label class="form-check">
                            <input type="checkbox" class="form-check-input" name="enabled_protocols[]" value="{{ $value }}" @checked(in_array($value, $enabledProtocols, true))>
                            <span class="form-check-label">{{ $label }}</span>
                        </label>
                    @endforeach
                    @error('enabled_protocols') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                </div>

                <hr>

                <h3 class="card-title">{{ t('Provider OAuth') }}</h3>
                <p class="text-secondary">{{ t('App registration condivisa usata dal pulsante "Connetti" quando un utente sceglie l\'autenticazione OAuth per una propria casella.') }}</p>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="google_oauth_client_id" class="form-label">{{ t('Google — Client ID') }}</label>
                        <input type="text" id="google_oauth_client_id" name="google_oauth_client_id" value="{{ old('google_oauth_client_id', $setting->google_oauth_client_id) }}" class="form-control @error('google_oauth_client_id') is-invalid @enderror">
                        @error('google_oauth_client_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="google_oauth_client_secret" class="form-label">{{ t('Google — Client secret') }}</label>
                        <input type="password" id="google_oauth_client_secret" name="google_oauth_client_secret" class="form-control @error('google_oauth_client_secret') is-invalid @enderror" placeholder="{{ $setting->google_oauth_client_secret ? t('Lascia vuoto per non modificarlo') : '' }}">
                        @error('google_oauth_client_secret') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="microsoft_oauth_client_id" class="form-label">{{ t('Microsoft 365 — Client ID') }}</label>
                        <input type="text" id="microsoft_oauth_client_id" name="microsoft_oauth_client_id" value="{{ old('microsoft_oauth_client_id', $setting->microsoft_oauth_client_id) }}" class="form-control @error('microsoft_oauth_client_id') is-invalid @enderror">
                        @error('microsoft_oauth_client_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="microsoft_oauth_client_secret" class="form-label">{{ t('Microsoft 365 — Client secret') }}</label>
                        <input type="password" id="microsoft_oauth_client_secret" name="microsoft_oauth_client_secret" class="form-control @error('microsoft_oauth_client_secret') is-invalid @enderror" placeholder="{{ $setting->microsoft_oauth_client_secret ? t('Lascia vuoto per non modificarlo') : '' }}">
                        @error('microsoft_oauth_client_secret') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <p class="text-secondary small">
                    {{ t('Redirect URI da registrare presso ciascun provider') }}:
                    <code>{{ route('mail.oauth.callback', 'google') }}</code>,
                    <code>{{ route('mail.oauth.callback', 'microsoft') }}</code>
                </p>
            </form>
        </div>
    </div>
@endsection
