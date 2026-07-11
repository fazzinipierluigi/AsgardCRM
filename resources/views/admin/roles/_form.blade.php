@php $isEdit = $role !== null; $isSystem = $role?->is_system ?? false; @endphp

<div class="mb-3">
    <label for="name" class="form-label">{{ __('Nome') }}</label>
    <input
        type="text"
        id="name"
        name="name"
        value="{{ old('name', $role?->name) }}"
        class="form-control @error('name') is-invalid @enderror"
    >
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="slug" class="form-label">{{ __('Slug') }}</label>
    <input
        type="text"
        id="slug"
        name="slug"
        value="{{ old('slug', $role?->slug) }}"
        class="form-control @error('slug') is-invalid @enderror"
        @if ($isSystem) readonly @endif
    >
    @if ($isSystem)
        <small class="form-hint">{{ __('Lo slug di un ruolo di sistema non può essere modificato.') }}</small>
    @endif
    @error('slug')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">{{ __('Permessi') }}</label>
    @foreach ($permissions as $group => $groupPermissions)
        <div class="mb-2">
            <div class="fw-bold text-uppercase small text-muted">{{ $group }}</div>
            @foreach ($groupPermissions as $permission)
                <label class="form-check">
                    <input
                        type="checkbox"
                        class="form-check-input"
                        name="permissions[]"
                        value="{{ $permission->key }}"
                        @checked(in_array($permission->key, old('permissions', $rolePermissionKeys ?? [])))
                    >
                    <span class="form-check-label">{{ $permission->name ?? $permission->key }}</span>
                </label>
            @endforeach
        </div>
    @endforeach
    @if ($permissions->isEmpty())
        <div class="text-muted">{{ __('Nessun permesso disponibile.') }}</div>
    @endif
</div>
