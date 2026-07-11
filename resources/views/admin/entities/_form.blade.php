<div class="mb-3">
    <label for="name" class="form-label">{{ t('Nome') }}</label>
    <input
        type="text"
        id="name"
        name="name"
        value="{{ old('name', $entity?->name) }}"
        class="form-control @error('name') is-invalid @enderror"
    >
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@if ($entity)
    <div class="mb-3">
        <label for="slug" class="form-label">{{ t('Slug') }}</label>
        <input type="text" id="slug" value="{{ $entity->slug }}" class="form-control" readonly>
        <small class="form-hint">{{ t('Lo slug non può essere modificato dopo la creazione.') }}</small>
    </div>
@endif

<div class="mb-3">
    <label for="icon" class="form-label">{{ t('Icona') }}</label>
    <input
        type="text"
        id="icon"
        name="icon"
        value="{{ old('icon', $entity?->icon) }}"
        class="form-control @error('icon') is-invalid @enderror"
        placeholder="ti ti-users"
    >
    <small class="form-hint">{{ t('Classe icona Tabler mostrata nel menu, es. "ti ti-users".') }}</small>
    @error('icon')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
