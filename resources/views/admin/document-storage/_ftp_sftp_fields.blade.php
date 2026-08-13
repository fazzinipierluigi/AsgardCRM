@php
    $field = fn (string $name) => $prefix.$name;
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="{{ $field('host') }}" class="form-label">{{ t('Host') }}</label>
        <input type="text" id="{{ $field('host') }}" name="{{ $field('host') }}" value="{{ $value($field('host')) }}" class="form-control @error($field('host')) is-invalid @enderror">
        @error($field('host')) <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-3">
        <label for="{{ $field('port') }}" class="form-label">{{ t('Porta') }}</label>
        <input type="number" id="{{ $field('port') }}" name="{{ $field('port') }}" value="{{ $value($field('port'), $defaultPort) }}" class="form-control @error($field('port')) is-invalid @enderror">
        @error($field('port')) <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-3">
        <label for="{{ $field('root') }}" class="form-label">{{ t('Cartella radice') }}</label>
        <input type="text" id="{{ $field('root') }}" name="{{ $field('root') }}" value="{{ $value($field('root')) }}" class="form-control @error($field('root')) is-invalid @enderror" placeholder="/documenti">
        @error($field('root')) <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="{{ $field('username') }}" class="form-label">{{ t('Utente') }}</label>
        <input type="text" id="{{ $field('username') }}" name="{{ $field('username') }}" value="{{ $value($field('username')) }}" class="form-control @error($field('username')) is-invalid @enderror">
        @error($field('username')) <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="{{ $field('password') }}" class="form-label">{{ t('Password') }}</label>
        <input type="password" id="{{ $field('password') }}" name="{{ $field('password') }}" class="form-control @error($field('password')) is-invalid @enderror" placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarla') : '' }}">
        @error($field('password')) <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

@if ($showSsl)
    <div class="mb-3 form-check form-switch">
        <input type="checkbox" id="{{ $field('ssl') }}" name="{{ $field('ssl') }}" value="1" class="form-check-input" @checked($value($field('ssl')))>
        <label for="{{ $field('ssl') }}" class="form-check-label">{{ t('Usa SSL/TLS') }}</label>
    </div>
@endif
