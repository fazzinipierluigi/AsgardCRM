@php
    // old('config', ...): 'config' is never an actual submitted field
    // name (the form submits imap_host/imap_username/etc individually,
    // never a nested "config" key), so this always falls through to the
    // given default — the account's real saved config — on every load,
    // both a fresh GET and a validation-error redirect back. Do NOT use
    // old(null, ...): with a null key, Laravel's old() ignores the
    // default entirely and returns the (empty, on a fresh GET) flashed
    // old-input bucket as-is — every field silently read as unset.
    $config = old('config', $account?->config ?? []);
    // Form field names for imap_/pop3_/exchange_ are prefixed to avoid
    // id/name collisions between fieldsets coexisting on this page; the
    // stored config uses plain keys for those. The smtp_ fieldset keeps
    // its prefix in storage too, since it coexists in the same config
    // array as the read-leg fields — see MailAccountController::
    // prefixedConfig()'s docblock.
    $value = function (string $field) use ($config) {
        $configKey = preg_replace('/^(imap|pop3|exchange)_/', '', $field);

        return old($field, $config[$configKey] ?? null);
    };
    $isEdit = $account !== null;
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">{{ t('Nome') }}</label>
        <input type="text" id="name" name="name" value="{{ old('name', $account?->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="{{ t('Es. Lavoro') }}">
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="email_address" class="form-label">{{ t('Indirizzo e-mail') }}</label>
        <input type="email" id="email_address" name="email_address" value="{{ old('email_address', $account?->email_address) }}" class="form-control @error('email_address') is-invalid @enderror">
        @error('email_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="protocol" class="form-label">{{ t('Protocollo') }}</label>
        <select id="protocol" name="protocol" class="form-select @error('protocol') is-invalid @enderror" data-testid="mail-account-protocol-select" @if ($isEdit) disabled @endif>
            @foreach (\Fazzinipierluigi\AsgardCRM\Enums\MailAccountProtocol::options() as $value2 => $label)
                <option value="{{ $value2 }}" @selected(old('protocol', $account?->protocol?->value) === $value2)>{{ $label }}</option>
            @endforeach
        </select>
        @if ($isEdit)
            <input type="hidden" name="protocol" value="{{ $account->protocol->value }}">
            <small class="form-hint">{{ t('Il protocollo di una casella non può essere modificato dopo la creazione.') }}</small>
        @endif
        @error('protocol') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3 d-flex align-items-end">
        <label class="form-check">
            <input type="checkbox" id="is_active" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $account?->is_active ?? true))>
            <span class="form-check-label">{{ t('Attiva') }}</span>
        </label>
    </div>
</div>

<div class="row" data-mail-account-protocol="imap">
    <div class="col-md-6 mb-3">
        <label for="auth_method" class="form-label">{{ t('Tipo di autenticazione') }}</label>
        <select id="auth_method" name="auth_method" class="form-select @error('auth_method') is-invalid @enderror" data-testid="mail-account-auth-method-select">
            @foreach (\Fazzinipierluigi\AsgardCRM\Enums\MailAuthMethod::options() as $value2 => $label)
                <option value="{{ $value2 }}" @selected(old('auth_method', $account?->auth_method?->value ?? 'password') === $value2)>{{ $label }}</option>
            @endforeach
        </select>
        @error('auth_method') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="mail_signature_id" class="form-label">{{ t('Firma') }}</label>
        <select id="mail_signature_id" name="mail_signature_id" class="form-select @error('mail_signature_id') is-invalid @enderror" data-testid="mail-account-signature-select">
            <option value="">{{ t('Nessuna') }}</option>
            @foreach ($signatures as $signature)
                <option value="{{ $signature->id }}" @selected((string) old('mail_signature_id', $account?->mail_signature_id) === (string) $signature->id)>{{ $signature->name }}</option>
            @endforeach
        </select>
        @error('mail_signature_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<fieldset data-mail-account-oauth class="mb-3">
    @if ($isEdit)
        @if ($account->isOAuthConnected())
            <div class="alert alert-success d-flex align-items-center gap-2" data-testid="mail-account-oauth-connected">
                {!! icon('circle-check') !!}
                {{ t('Connesso.') }}
            </div>
        @else
            <div class="alert alert-warning d-flex align-items-center gap-2" data-testid="mail-account-oauth-disconnected">
                {!! icon('alert-triangle') !!}
                {{ t('Non ancora connesso: salva le modifiche, poi completa la connessione qui sotto.') }}
            </div>
        @endif
        <div data-mail-account-oauth-connect="google_oauth">
            <a href="{{ route('mail.oauth.connect', [$account, 'google']) }}" class="btn btn-outline-secondary" data-testid="mail-account-oauth-connect-google">{{ t('Connetti con Google') }}</a>
        </div>
        <div data-mail-account-oauth-connect="microsoft_oauth">
            <a href="{{ route('mail.oauth.connect', [$account, 'microsoft']) }}" class="btn btn-outline-secondary" data-testid="mail-account-oauth-connect-microsoft">{{ t('Connetti con Microsoft 365') }}</a>
        </div>
    @else
        <small class="form-hint">{{ t('Salva la casella per poter completare la connessione OAuth.') }}</small>
    @endif
</fieldset>

<fieldset data-mail-account-protocol="exchange" class="mb-3">
    <div class="mb-3">
        <label for="mail_connector_id" class="form-label">{{ t('Connector aziendale') }}</label>
        <select id="mail_connector_id" name="mail_connector_id" class="form-select @error('mail_connector_id') is-invalid @enderror" data-testid="mail-account-connector-select">
            <option value="">{{ t('Nessuno — inserisci le credenziali della casella') }}</option>
            @foreach ($connectors as $connector)
                <option value="{{ $connector->id }}" @selected((string) old('mail_connector_id', $account?->mail_connector_id) === (string) $connector->id)>{{ $connector->name }}</option>
            @endforeach
        </select>
        @error('mail_connector_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <small class="form-hint">{{ t('Se il tuo amministratore ha configurato un connector Exchange aziendale, selezionalo per accedere senza inserire una password personale.') }}</small>
    </div>

    <fieldset data-mail-account-direct-exchange class="mb-3">
        <legend class="fs-5">{{ t('Credenziali dirette (EWS)') }}</legend>
        <div class="mb-3">
            <label for="exchange_ews_url" class="form-label">{{ t('URL EWS') }}</label>
            <input type="url" id="exchange_ews_url" name="exchange_ews_url" value="{{ $value('exchange_ews_url') }}" class="form-control @error('exchange_ews_url') is-invalid @enderror" placeholder="https://mail.example.com/EWS/Exchange.asmx">
            @error('exchange_ews_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="exchange_username" class="form-label">{{ t('Username') }}</label>
                <input type="text" id="exchange_username" name="exchange_username" value="{{ $value('exchange_username') }}" class="form-control @error('exchange_username') is-invalid @enderror">
                @error('exchange_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6 mb-3">
                <label for="exchange_password" class="form-label">{{ t('Password') }}</label>
                <input type="password" id="exchange_password" name="exchange_password" class="form-control @error('exchange_password') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarla') : '' }}">
                @error('exchange_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <div class="mb-3 form-check form-switch">
            <input type="checkbox" id="exchange_use_ntlm" name="exchange_use_ntlm" value="1" class="form-check-input" @checked($value('exchange_use_ntlm'))>
            <label for="exchange_use_ntlm" class="form-check-label">{{ t('Usa NTLM') }}</label>
        </div>
    </fieldset>
</fieldset>

<fieldset data-mail-account-protocol="imap" data-mail-account-imap-server-fields class="mb-3">
    <legend class="fs-5">{{ t('Server IMAP') }}</legend>
    @include('crm::mail.accounts._server_fields', ['value' => $value, 'isEdit' => $isEdit, 'prefix' => 'imap_', 'defaultPort' => 993])
</fieldset>

<fieldset data-mail-account-protocol="pop3" class="mb-3">
    <legend class="fs-5">{{ t('Server POP3') }}</legend>
    @include('crm::mail.accounts._server_fields', ['value' => $value, 'isEdit' => $isEdit, 'prefix' => 'pop3_', 'defaultPort' => 995])
</fieldset>

<fieldset data-mail-account-smtp class="mb-3">
    <legend class="fs-5">{{ t('Invio (SMTP)') }}</legend>
    <small class="form-hint d-block mb-2">{{ t('Serve per inviare posta da questa casella — non richiesto se usi un connector aziendale.') }}</small>
    @include('crm::mail.accounts._server_fields', ['value' => $value, 'isEdit' => $isEdit, 'prefix' => 'smtp_', 'defaultPort' => 587])
</fieldset>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var protocolSelect = document.getElementById('protocol');
        var connectorSelect = document.getElementById('mail_connector_id');
        var authMethodSelect = document.getElementById('auth_method');
        var protocolFieldsets = document.querySelectorAll('[data-mail-account-protocol]');
        var smtpFieldset = document.querySelector('[data-mail-account-smtp]');
        var directExchangeFieldset = document.querySelector('[data-mail-account-direct-exchange]');
        var oauthFieldset = document.querySelector('[data-mail-account-oauth]');
        var oauthConnectLinks = document.querySelectorAll('[data-mail-account-oauth-connect]');

        function syncVisibility() {
            var protocol = protocolSelect.value;

            // OAuth is only wired up for IMAP (see MailAuthMethod's own
            // docblock) — switching away from it silently resets the
            // selection back to Password instead of letting the user
            // submit a combination the backend would just reject.
            if (protocol !== 'imap' && authMethodSelect.value !== 'password') {
                // .value alone only updates the hidden native <select>,
                // not Tom Select's own rendered UI (every plain <select>
                // in this app is auto-wrapped, see tom-select.js) — this
                // shared helper keeps both in sync.
                window.setSelectValue(authMethodSelect, 'password');
            }

            var isOAuth = protocol === 'imap' && authMethodSelect.value !== 'password';

            protocolFieldsets.forEach(function (fieldset) {
                var matchesProtocol = fieldset.getAttribute('data-mail-account-protocol') === protocol;
                var hideForOAuth = fieldset.hasAttribute('data-mail-account-imap-server-fields') && isOAuth;
                fieldset.style.display = matchesProtocol && ! hideForOAuth ? '' : 'none';
            });

            smtpFieldset.style.display = (protocol === 'imap' || protocol === 'pop3') && ! isOAuth ? '' : 'none';
            directExchangeFieldset.style.display = (protocol === 'exchange' && !connectorSelect.value) ? '' : 'none';
            oauthFieldset.style.display = isOAuth ? '' : 'none';

            oauthConnectLinks.forEach(function (link) {
                link.style.display = link.getAttribute('data-mail-account-oauth-connect') === authMethodSelect.value ? '' : 'none';
            });
        }

        protocolSelect.addEventListener('change', syncVisibility);
        connectorSelect.addEventListener('change', syncVisibility);
        authMethodSelect.addEventListener('change', syncVisibility);
        syncVisibility();
    });
</script>
