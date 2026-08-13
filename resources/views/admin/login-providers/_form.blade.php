@php
    $config = old('config', $loginProvider?->config ?? []);
    $value = fn (string $key, mixed $default = null) => old($key, $config[$key] ?? $default);
    $isEdit = $loginProvider !== null;
@endphp

<div class="mb-3">
    <label for="name" class="form-label">{{ t('Nome') }}</label>
    <input
        type="text"
        id="name"
        name="name"
        value="{{ old('name', $loginProvider?->name) }}"
        class="form-control @error('name') is-invalid @enderror"
    >
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="type" class="form-label">{{ t('Tipo') }}</label>
    <select
        id="type"
        name="type"
        class="form-select @error('type') is-invalid @enderror"
        data-testid="login-provider-type-select"
        @if ($isEdit) disabled @endif
    >
        <option value="ldap" @selected(old('type', $loginProvider?->type) === 'ldap')>{{ t('LDAP') }}</option>
        <option value="oauth" @selected(old('type', $loginProvider?->type) === 'oauth')>{{ t('OAuth') }}</option>
        <option value="oidc" @selected(old('type', $loginProvider?->type) === 'oidc')>{{ t('OpenID Connect') }}</option>
        <option value="saml" @selected(old('type', $loginProvider?->type) === 'saml')>{{ t('SAML') }}</option>
    </select>
    @if ($isEdit)
        <input type="hidden" name="type" value="{{ $loginProvider->type }}">
        <small class="form-hint">{{ t('Il tipo di un provider non può essere modificato dopo la creazione.') }}</small>
    @endif
    @error('type')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3 form-check form-switch">
    <input
        type="checkbox"
        id="is_active"
        name="is_active"
        value="1"
        class="form-check-input"
        @checked(old('is_active', $loginProvider?->is_active ?? true))
    >
    <label for="is_active" class="form-check-label">{{ t('Attivo') }}</label>
</div>

<fieldset data-provider-config="ldap" class="mb-3">
    <legend class="fs-4">{{ t('LDAP') }}</legend>

    <div class="mb-3">
        <label for="host" class="form-label">{{ t('Host') }}</label>
        <input type="text" id="host" name="host" value="{{ $value('host') }}" class="form-control @error('host') is-invalid @enderror">
        @error('host') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="port" class="form-label">{{ t('Porta') }}</label>
        <input type="number" id="port" name="port" value="{{ $value('port', 389) }}" class="form-control @error('port') is-invalid @enderror">
        @error('port') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="base_dn" class="form-label">{{ t('Base DN') }}</label>
        <input type="text" id="base_dn" name="base_dn" value="{{ $value('base_dn') }}" class="form-control @error('base_dn') is-invalid @enderror" placeholder="dc=example,dc=com">
        @error('base_dn') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="bind_dn" class="form-label">{{ t('Bind DN') }}</label>
        <input type="text" id="bind_dn" name="bind_dn" value="{{ $value('bind_dn') }}" class="form-control @error('bind_dn') is-invalid @enderror" placeholder="cn=admin,dc=example,dc=com">
        @error('bind_dn') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="bind_password" class="form-label">{{ t('Bind password') }}</label>
        <input type="password" id="bind_password" name="bind_password" class="form-control @error('bind_password') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarla') : '' }}">
        @error('bind_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3 form-check form-switch">
        <input type="checkbox" id="use_tls" name="use_tls" value="1" class="form-check-input" @checked($value('use_tls'))>
        <label for="use_tls" class="form-check-label">{{ t('Usa TLS') }}</label>
    </div>

    <div class="mb-3">
        <label for="user_filter" class="form-label">{{ t('Filtro utente') }}</label>
        <input type="text" id="user_filter" name="user_filter" value="{{ $value('user_filter', '(uid=%s)') }}" class="form-control @error('user_filter') is-invalid @enderror">
        @error('user_filter') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="attr_username" class="form-label">{{ t('Attributo username') }}</label>
        <input type="text" id="attr_username" name="attr_username" value="{{ $value('attr_username', 'uid') }}" class="form-control @error('attr_username') is-invalid @enderror">
        @error('attr_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="ldap_attr_email" class="form-label">{{ t('Attributo email') }}</label>
        <input type="text" id="ldap_attr_email" name="attr_email" value="{{ $value('attr_email', 'mail') }}" class="form-control @error('attr_email') is-invalid @enderror">
        @error('attr_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="ldap_attr_name" class="form-label">{{ t('Attributo nome') }}</label>
        <input type="text" id="ldap_attr_name" name="attr_name" value="{{ $value('attr_name', 'cn') }}" class="form-control @error('attr_name') is-invalid @enderror">
        @error('attr_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</fieldset>

<fieldset data-provider-config="oauth oidc" class="mb-3">
    <legend class="fs-4">{{ t('OAuth / OpenID Connect') }}</legend>

    <div class="mb-3">
        <label for="client_id" class="form-label">{{ t('Client ID') }}</label>
        <input type="text" id="client_id" name="client_id" value="{{ $value('client_id') }}" class="form-control @error('client_id') is-invalid @enderror">
        @error('client_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="client_secret" class="form-label">{{ t('Client secret') }}</label>
        <input type="password" id="client_secret" name="client_secret" class="form-control @error('client_secret') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarlo') : '' }}">
        @error('client_secret') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="authorize_url" class="form-label">{{ t('Authorize URL') }}</label>
        <input type="url" id="authorize_url" name="authorize_url" value="{{ $value('authorize_url') }}" class="form-control @error('authorize_url') is-invalid @enderror">
        @error('authorize_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="token_url" class="form-label">{{ t('Token URL') }}</label>
        <input type="url" id="token_url" name="token_url" value="{{ $value('token_url') }}" class="form-control @error('token_url') is-invalid @enderror">
        @error('token_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="userinfo_url" class="form-label">{{ t('Userinfo URL') }}</label>
        <input type="url" id="userinfo_url" name="userinfo_url" value="{{ $value('userinfo_url') }}" class="form-control @error('userinfo_url') is-invalid @enderror">
        @error('userinfo_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="scopes" class="form-label">{{ t('Scope') }}</label>
        <input type="text" id="scopes" name="scopes" value="{{ $value('scopes') }}" class="form-control @error('scopes') is-invalid @enderror" placeholder="openid email profile">
        @error('scopes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</fieldset>

<fieldset data-provider-config="saml" class="mb-3">
    <legend class="fs-4">{{ t('SAML') }}</legend>

    <div class="mb-3">
        <label for="idp_entity_id" class="form-label">{{ t('Entity ID IdP') }}</label>
        <input type="text" id="idp_entity_id" name="idp_entity_id" value="{{ $value('idp_entity_id') }}" class="form-control @error('idp_entity_id') is-invalid @enderror">
        @error('idp_entity_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="idp_sso_url" class="form-label">{{ t('SSO URL IdP') }}</label>
        <input type="url" id="idp_sso_url" name="idp_sso_url" value="{{ $value('idp_sso_url') }}" class="form-control @error('idp_sso_url') is-invalid @enderror">
        @error('idp_sso_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="idp_x509_cert" class="form-label">{{ t('Certificato X.509 IdP') }}</label>
        <textarea id="idp_x509_cert" name="idp_x509_cert" rows="5" class="form-control @error('idp_x509_cert') is-invalid @enderror">{{ $value('idp_x509_cert') }}</textarea>
        @error('idp_x509_cert') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="sp_entity_id" class="form-label">{{ t('Entity ID SP') }}</label>
        <input type="text" id="sp_entity_id" name="sp_entity_id" value="{{ $value('sp_entity_id') }}" class="form-control @error('sp_entity_id') is-invalid @enderror" placeholder="{{ $isEdit && Route::has('login.saml.metadata') ? route('login.saml.metadata', $loginProvider) : t('Lascia vuoto per usare l\'URL dei metadati SP') }}">
        @error('sp_entity_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="saml_attr_email" class="form-label">{{ t('Attributo email') }}</label>
        <input type="text" id="saml_attr_email" name="attr_email" value="{{ $value('attr_email') }}" class="form-control @error('attr_email') is-invalid @enderror">
        @error('attr_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="saml_attr_name" class="form-label">{{ t('Attributo nome') }}</label>
        <input type="text" id="saml_attr_name" name="attr_name" value="{{ $value('attr_name') }}" class="form-control @error('attr_name') is-invalid @enderror">
        @error('attr_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</fieldset>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var typeSelect = document.getElementById('type');
        var fieldsets = document.querySelectorAll('[data-provider-config]');

        function syncVisibility() {
            var type = typeSelect.value;
            fieldsets.forEach(function (fieldset) {
                var types = fieldset.getAttribute('data-provider-config').split(' ');
                fieldset.style.display = types.includes(type) ? '' : 'none';
            });
        }

        typeSelect.addEventListener('change', syncVisibility);
        syncVisibility();
    });
</script>
