<div class="mb-3">
    <label for="key" class="form-label">{{ __('Chiave') }}</label>
    <input
        type="text"
        id="key"
        name="key"
        value="{{ old('key', $permission?->key) }}"
        class="form-control @error('key') is-invalid @enderror"
        placeholder="es. contacts.manage"
    >
    @error('key')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="name" class="form-label">{{ __('Nome') }}</label>
    <input
        type="text"
        id="name"
        name="name"
        value="{{ old('name', $permission?->name) }}"
        class="form-control @error('name') is-invalid @enderror"
    >
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="description" class="form-label">{{ __('Descrizione') }}</label>
    <textarea
        id="description"
        name="description"
        class="form-control @error('description') is-invalid @enderror"
        rows="3"
    >{{ old('description', $permission?->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
