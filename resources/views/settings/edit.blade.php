@extends('layouts.base')

@section('title', __('Impostazioni'))

@section('content')
    <div class="row">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">{{ __('Impostazioni personali') }}</h3>
                </div>
                <div class="card-body">
                    @if (session('status') === 'settings-updated')
                        <div class="alert alert-success" data-testid="settings-updated">{{ __('Impostazioni aggiornate.') }}</div>
                    @endif

                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="username" class="form-label">{{ __('Username') }}</label>
                            <input type="text" id="username" class="form-control" value="{{ $user->username }}" disabled>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('Nome') }}</label>
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

                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
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

                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('Nuova password') }}</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="{{ __('Lascia vuoto per non modificarla') }}"
                            >
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">{{ __('Conferma password') }}</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control">
                        </div>

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary">{{ __('Salva') }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">{{ __('Preferenze') }}</h3>
                </div>
                <div class="card-body">
                    @if (session('status') === 'preferences-updated')
                        <div class="alert alert-success" data-testid="preferences-updated">{{ __('Preferenze aggiornate.') }}</div>
                    @endif

                    <form action="{{ route('settings.preferences.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        @foreach (config('preferences') as $key => $preference)
                            <div class="mb-3">
                                <label for="{{ $key }}" class="form-label">{{ __(ucfirst(str_replace('_', ' ', $key))) }}</label>
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

                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary" data-testid="preferences-submit">{{ __('Salva preferenze') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
