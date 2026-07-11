<div class="mb-3">
    <label for="key" class="form-label">{{ t('Chiave') }}</label>
    <input
        type="text"
        id="key"
        name="key"
        value="{{ old('key', $translation?->key) }}"
        class="form-control @error('key') is-invalid @enderror"
        placeholder="es. dashboard.welcome"
    >
    @error('key')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="language" class="form-label">{{ t('Lingua') }}</label>
    <select id="language" name="language" class="form-select @error('language') is-invalid @enderror">
        @foreach (config('preferences.language.options') as $value => $label)
            <option value="{{ $value }}" @selected(old('language', $translation?->language) === $value)>
                {{ $label }}
            </option>
        @endforeach
    </select>
    @error('language')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="value" class="form-label">{{ t('Valore') }}</label>
    <textarea
        id="value"
        name="value"
        class="form-control @error('value') is-invalid @enderror"
        rows="3"
    >{{ old('value', $translation?->value) }}</textarea>
    @error('value')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
