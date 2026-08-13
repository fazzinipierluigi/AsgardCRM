@php $isEdit = $signature !== null; @endphp

<div id="mail-signature-form-app" data-testid="mail-signature-form-app">
    <div class="mb-3">
        <label for="name" class="form-label">{{ t('Nome') }}</label>
        <input type="text" id="name" name="name" value="{{ old('name', $signature?->name) }}" class="form-control @error('name') is-invalid @enderror" placeholder="{{ t('Es. Firma commerciale') }}">
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-3">
        <label class="form-label">{{ t('Corpo della firma') }}</label>
        <div class="btn-list mb-2" data-testid="mail-signature-placeholders">
            <span class="text-secondary small align-self-center me-1">{{ t('Inserisci segnaposto') }}:</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-insert-placeholder="{{ '{{user.name}}' }}">{{ t('Nome') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-insert-placeholder="{{ '{{user.email}}' }}">{{ t('Email') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-insert-placeholder="{{ '{{user.phone}}' }}">{{ t('Telefono') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-insert-placeholder="{{ '{{user.job_title}}' }}">{{ t('Ruolo/carica') }}</button>
        </div>
        <textarea id="body_html" name="body_html" data-testid="mail-signature-body">{{ old('body_html', $signature?->body_html) }}</textarea>
        @error('body_html') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <small class="form-hint">{{ t('I segnaposto vengono sostituiti con i dati reali dell\'utente quando la firma viene usata in un messaggio.') }}</small>
    </div>
</div>
