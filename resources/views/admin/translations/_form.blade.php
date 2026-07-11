@php $keyReadonly ??= false; $values ??= collect(); @endphp

<div class="mb-3">
    <label for="key" class="form-label">{{ t('Chiave') }}</label>
    <input
        type="text"
        id="key"
        name="key"
        value="{{ old('key', $translation?->key) }}"
        class="form-control @error('key') is-invalid @enderror"
        placeholder="es. dashboard.welcome"
        @if ($keyReadonly) readonly @endif
    >
    @error('key')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@error('values')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror

@foreach ($languages as $language)
    <div class="mb-3">
        <label for="value_{{ $language->code }}" class="form-label">{{ $language->name }} ({{ $language->code }})</label>
        <textarea
            id="value_{{ $language->code }}"
            name="values[{{ $language->code }}]"
            class="form-control @error('values.'.$language->code) is-invalid @enderror"
            rows="2"
        >{{ old('values.'.$language->code, $values[$language->code] ?? '') }}</textarea>
        @error('values.'.$language->code)
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
@endforeach
