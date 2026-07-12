@php $isEdit = $user !== null; @endphp

<div class="row">
    <div class="col-4 mb-3">
        <label for="name" class="form-label">{{ t('Nome') }}</label>
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

    <div class="col-4 mb-3">
        <label for="username" class="form-label">{{ t('Username') }}</label>
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

    <div class="col-4 mb-3">
        <label for="email" class="form-label">{{ t('Email') }}</label>
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

    <div class="col-12 mb-3">
        <label for="roles" class="form-label">{{ t('Ruoli') }}</label>
        <select
            id="roles"
            name="roles[]"
            multiple
            class="form-select @error('roles') is-invalid @enderror"
            data-testid="user-roles-select"
        >
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected(in_array($role->id, old('roles', $userRoleIds)))>{{ $role->name }}</option>
            @endforeach
        </select>
        @error('roles')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    @php $selectedProviderId = old('login_provider_id', $user?->login_provider_id ?? $loginProviders->firstWhere('slug', 'local')?->id); @endphp

    <div class="col-4 mb-3">
        <label for="login_provider_id" class="form-label">{{ t('Login provider') }}</label>
        <select
            id="login_provider_id"
            name="login_provider_id"
            class="form-select @error('login_provider_id') is-invalid @enderror"
            data-testid="user-login-provider-select"
        >
            @foreach ($loginProviders as $provider)
                <option value="{{ $provider->id }}" data-type="{{ $provider->type }}" @selected((string) $selectedProviderId === (string) $provider->id)>{{ $provider->name }}</option>
            @endforeach
        </select>
        @error('login_provider_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-4 mb-3">
        <label for="password" class="form-label">{{ t('Password') }}</label>
        <input
            type="password"
            id="password"
            name="password"
            class="form-control @error('password') is-invalid @enderror"
            @if (! $isEdit) required @endif
            placeholder="{{ $isEdit ? t('Lascia vuoto per non modificarla') : '' }}"
        >
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-4 mb-3">
        <label for="password_confirmation" class="form-label">{{ t('Conferma password') }}</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control">
    </div>
</div>
