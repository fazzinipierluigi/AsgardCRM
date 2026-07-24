@php
    $config = $endpoint?->config ?? [];
    $value = fn (string $key, mixed $default = null) => old($key, $config[$key] ?? $default);
    $isEdit = $endpoint !== null;
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="name" class="form-label">{{ t('Nome') }}</label>
        <input type="text" id="name" name="name" value="{{ old('name', $endpoint?->name) }}" class="form-control @error('name') is-invalid @enderror">
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="workflow_id" class="form-label">{{ t('Workflow (vuoto = globale, usabile da tutti)') }}</label>
        <select id="workflow_id" name="workflow_id" class="form-select @error('workflow_id') is-invalid @enderror">
            <option value="">{{ t('— Globale —') }}</option>
            @foreach ($workflows as $workflow)
                <option value="{{ $workflow->id }}" @selected((string) old('workflow_id', $endpoint?->workflow_id) === (string) $workflow->id)>{{ $workflow->name }}</option>
            @endforeach
        </select>
        @error('workflow_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3">
    <label for="base_url" class="form-label">{{ t('URL base') }}</label>
    <input type="text" id="base_url" name="base_url" value="{{ old('base_url', $endpoint?->base_url) }}" placeholder="https://api.example.com" class="form-control @error('base_url') is-invalid @enderror">
    @error('base_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label for="auth_type" class="form-label">{{ t('Autenticazione') }}</label>
    <select id="auth_type" name="auth_type" class="form-select @error('auth_type') is-invalid @enderror">
        <option value="none" @selected($value('auth_type', 'none') === 'none')>{{ t('Nessuna') }}</option>
        <option value="bearer" @selected($value('auth_type') === 'bearer')>{{ t('Bearer token') }}</option>
        <option value="basic" @selected($value('auth_type') === 'basic')>{{ t('Basic auth') }}</option>
        <option value="header" @selected($value('auth_type') === 'header')>{{ t('Header personalizzato') }}</option>
    </select>
    @error('auth_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3 auth-group" data-auth="bearer">
    <label for="token" class="form-label">{{ t('Token') }}</label>
    <input type="password" id="token" name="token" class="form-control @error('token') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarlo') : '' }}">
    @error('token') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="row auth-group" data-auth="basic">
    <div class="col-md-6 mb-3">
        <label for="username" class="form-label">{{ t('Username') }}</label>
        <input type="text" id="username" name="username" value="{{ $value('username') }}" class="form-control @error('username') is-invalid @enderror">
        @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="password" class="form-label">{{ t('Password') }}</label>
        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarla') : '' }}">
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row auth-group" data-auth="header">
    <div class="col-md-6 mb-3">
        <label for="header_name" class="form-label">{{ t('Nome header') }}</label>
        <input type="text" id="header_name" name="header_name" value="{{ $value('header_name') }}" placeholder="X-Api-Key" class="form-control @error('header_name') is-invalid @enderror">
        @error('header_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="header_value" class="form-label">{{ t('Valore header') }}</label>
        <input type="password" id="header_value" name="header_value" class="form-control @error('header_value') is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarlo') : '' }}">
        @error('header_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var authSelect = document.getElementById('auth_type');
        var groups = document.querySelectorAll('.auth-group');

        function syncVisibility() {
            var type = authSelect.value;
            groups.forEach(function (group) {
                group.style.display = group.getAttribute('data-auth') === type ? '' : 'none';
            });
        }

        authSelect.addEventListener('change', syncVisibility);
        syncVisibility();
    });
</script>
