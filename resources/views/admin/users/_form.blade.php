@php $isEdit = $user !== null; @endphp

<div class="mb-3">
    <label for="name" class="form-label">{{ __('Nome') }}</label>
    <input
        type="text"
        id="name"
        name="name"
        value="{{ old('name', $user?->name) }}"
        class="form-control @error('name') is-invalid @enderror"
    >
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="username" class="form-label">{{ __('Username') }}</label>
    <input
        type="text"
        id="username"
        name="username"
        value="{{ old('username', $user?->username) }}"
        class="form-control @error('username') is-invalid @enderror"
    >
    @error('username')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="email" class="form-label">{{ __('Email') }}</label>
    <input
        type="email"
        id="email"
        name="email"
        value="{{ old('email', $user?->email) }}"
        class="form-control @error('email') is-invalid @enderror"
    >
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="password" class="form-label">{{ __('Password') }}</label>
    <input
        type="password"
        id="password"
        name="password"
        class="form-control @error('password') is-invalid @enderror"
        @if (! $isEdit) required @endif
        placeholder="{{ $isEdit ? __('Lascia vuoto per non modificarla') : '' }}"
    >
    @error('password')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="password_confirmation" class="form-label">{{ __('Conferma password') }}</label>
    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control">
</div>

<div class="mb-3">
    <label class="form-label">{{ __('Ruoli') }}</label>
    @foreach ($roles as $role)
        <label class="form-check">
            <input
                type="checkbox"
                class="form-check-input"
                name="roles[]"
                value="{{ $role->id }}"
                @checked(in_array($role->id, old('roles', $userRoleIds)))
            >
            <span class="form-check-label">{{ $role->name }}</span>
        </label>
    @endforeach
</div>
