@php
    $config = old('config', $connector?->config ?? []);
    $value = fn (string $key, mixed $default = null) => old($key, $config[$key] ?? $default);
    $isEdit = $connector !== null;
@endphp

<div class="row">
    <div class="col-md-8 mb-3">
        <label for="name" class="form-label">{{ t('Nome') }}</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $connector?->name) }}"
            class="form-control @error('name') is-invalid @enderror"
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="type" class="form-label">{{ t('Tipo connettore') }}</label>
        <select
            id="type"
            name="type"
            class="form-select @error('type') is-invalid @enderror"
            data-testid="mail-connector-type-select"
            @if ($isEdit) disabled @endif
        >
            @foreach (\App\Enums\MailConnectorType::options() as $value2 => $label)
                <option value="{{ $value2 }}" @selected(old('type', $connector?->type?->value) === $value2)>{{ $label }}</option>
            @endforeach
        </select>
        @if ($isEdit)
            <input type="hidden" name="type" value="{{ $connector->type->value }}">
            <small class="form-hint">{{ t('Il tipo di un connettore non può essere modificato dopo la creazione.') }}</small>
        @endif
        @error('type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3 form-check form-switch">
    <input
        type="checkbox"
        id="is_active"
        name="is_active"
        value="1"
        class="form-check-input"
        @checked(old('is_active', $connector?->is_active ?? true))
    >
    <label for="is_active" class="form-check-label">{{ t('Attivo') }}</label>
</div>

<fieldset data-mail-connector-config="exchange_graph" class="mb-3">
    <legend class="fs-4">{{ t('Exchange (Microsoft Graph)') }}</legend>

    <div class="mb-3">
        <label for="tenant_id" class="form-label">{{ t('Tenant ID') }}</label>
        <input type="text" id="tenant_id" name="tenant_id" value="{{ $value('tenant_id') }}" class="form-control @error('tenant_id') is-invalid @enderror">
        @error('tenant_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="client_id" class="form-label">{{ t('Client ID') }}</label>
        <input type="text" id="client_id" name="client_id" value="{{ $value('client_id') }}" class="form-control @error('client_id') is-invalid @enderror">
        @error('client_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="client_secret" class="form-label">{{ t('Client secret') }}</label>
        <input type="password" id="client_secret" name="client_secret" class="form-control @error('client_secret') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarlo') : '' }}">
        @error('client_secret') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <small class="form-hint">{{ t('Richiede un app registration Microsoft Entra con permesso applicativo Mail.Read e Mail.Send e consenso admin.') }}</small>
    </div>
</fieldset>

<fieldset data-mail-connector-config="exchange_ews" class="mb-3">
    <legend class="fs-4">{{ t('Exchange (EWS on-premise)') }}</legend>

    <div class="mb-3">
        <label for="ews_url" class="form-label">{{ t('URL EWS') }}</label>
        <input type="url" id="ews_url" name="ews_url" value="{{ $value('ews_url') }}" class="form-control @error('ews_url') is-invalid @enderror" placeholder="https://mail.example.com/EWS/Exchange.asmx">
        @error('ews_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="username" class="form-label">{{ t('Username') }}</label>
        <input type="text" id="username" name="username" value="{{ $value('username') }}" class="form-control @error('username') is-invalid @enderror">
        @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">{{ t('Password') }}</label>
        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarla') : '' }}">
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <small class="form-hint">{{ t('Serve un account di servizio con diritto EWS Impersonation per accedere a più mailbox.') }}</small>
    </div>

    <div class="mb-3 form-check form-switch">
        <input type="checkbox" id="use_ntlm" name="use_ntlm" value="1" class="form-check-input" @checked($value('use_ntlm'))>
        <label for="use_ntlm" class="form-check-label">{{ t('Usa NTLM') }}</label>
        <div><small class="form-hint">{{ t('Best effort: preferire Basic auth quando disponibile.') }}</small></div>
    </div>
</fieldset>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var typeSelect = document.getElementById('type');
        var fieldsets = document.querySelectorAll('[data-mail-connector-config]');

        function syncVisibility() {
            var type = typeSelect.value;
            fieldsets.forEach(function (fieldset) {
                fieldset.style.display = fieldset.getAttribute('data-mail-connector-config') === type ? '' : 'none';
            });
        }

        typeSelect.addEventListener('change', syncVisibility);
        syncVisibility();
    });
</script>
