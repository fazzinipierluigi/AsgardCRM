@php
    $field = fn (string $name) => $prefix.$name;
@endphp

<div class="row">
    <div class="col-md-5 mb-3">
        <label for="{{ $field('host') }}" class="form-label">{{ t('Host') }}</label>
        <input type="text" id="{{ $field('host') }}" name="{{ $field('host') }}" value="{{ $value($field('host')) }}" class="form-control @error($field('host')) is-invalid @enderror">
        @error($field('host')) <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-3 mb-3">
        <label for="{{ $field('port') }}" class="form-label">{{ t('Porta') }}</label>
        <input type="number" id="{{ $field('port') }}" name="{{ $field('port') }}" value="{{ $value($field('port')) ?? $defaultPort }}" class="form-control @error($field('port')) is-invalid @enderror">
        @error($field('port')) <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="{{ $field('encryption') }}" class="form-label">{{ t('Crittografia') }}</label>
        <select id="{{ $field('encryption') }}" name="{{ $field('encryption') }}" class="form-select @error($field('encryption')) is-invalid @enderror">
            @foreach (\App\Enums\MailEncryption::options() as $value2 => $label)
                <option value="{{ $value2 }}" @selected($value($field('encryption')) === $value2)>{{ $label }}</option>
            @endforeach
        </select>
        @error($field('encryption')) <div class="invalid-feedback">{{ $message }}</div> @enderror
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
