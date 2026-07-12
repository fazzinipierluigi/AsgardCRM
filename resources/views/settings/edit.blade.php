@extends('layouts.base')

@section('title', t('Impostazioni'))

@section('buttons')
    <button type="submit" form="settings-form" class="btn btn-primary" data-testid="settings-submit">{{ t('Salva') }}</button>
    <button type="submit" form="preferences-form" class="btn btn-primary" data-testid="preferences-submit">{{ t('Salva preferenze') }}</button>
@endsection

@section('content')
    <div class="row row-cards">
        <div class="col-12 col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ t('Impostazioni personali') }}</h3>
                </div>
                <div class="card-body">
                    @if (session('status') === 'settings-updated')
                        <div class="alert alert-success" data-testid="settings-updated">{{ t('Impostazioni aggiornate.') }}</div>
                    @endif

                    <form action="{{ route('settings.update') }}" method="POST" id="settings-form">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-12 col-lg-6 mb-3">
                                <label for="username" class="form-label">{{ t('Username') }}</label>
                                <input type="text" id="username" class="form-control" value="{{ $user->username }}" disabled>
                            </div>

                            <div class="col-12 col-lg-6 mb-3">
                                <label for="name" class="form-label">{{ t('Nome') }}</label>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    value="{{ old('name', $user->name) }}"
                                    class="form-control @error('name') is-invalid @enderror"
                                >
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 mb-3">
                                <label for="email" class="form-label">{{ t('Email') }}</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="{{ old('email', $user->email) }}"
                                    class="form-control @error('email') is-invalid @enderror"
                                >
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-lg-6 mb-3">
                                <label for="password" class="form-label">{{ t('Nuova password') }}</label>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    placeholder="{{ t('Lascia vuoto per non modificarla') }}"
                                >
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-lg-6 mb-3">
                                <label for="password_confirmation" class="form-label">{{ t('Conferma password') }}</label>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control">
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ t('Preferenze') }}</h3>
                </div>
                <div class="card-body">
                    @if (session('status') === 'preferences-updated')
                        <div class="alert alert-success" data-testid="preferences-updated">{{ t('Preferenze aggiornate.') }}</div>
                    @endif

                    <form action="{{ route('settings.preferences.update') }}" method="POST" id="preferences-form">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            @foreach (preferences() as $key => $preference)
                                <div class="col-12 col-lg-6 mb-3">
                                    <label for="{{ $key }}" class="form-label">{{ t(ucfirst(str_replace('_', ' ', $key))) }}</label>
                                    <select
                                        id="{{ $key }}"
                                        name="{{ $key }}"
                                        class="form-select @error($key) is-invalid @enderror"
                                        data-testid="preference-{{ $key }}"
                                    >
                                        @foreach ($preference['options'] as $value => $label)
                                            <option value="{{ $value }}" @selected(old($key, $preferences[$key]) === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error($key)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endforeach
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
