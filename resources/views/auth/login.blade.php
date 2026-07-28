<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ t('Login') }} - {{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="border-top-wide border-primary d-flex flex-column">
        <div class="page page-center">
            <div class="container container-tight py-4">
                <div class="text-center mb-4">
                    <a href="{{ url('/') }}" class="navbar-brand navbar-brand-autodark d-flex align-items-center justify-content-center gap-2">
                        <img src="{{ asset('logo.svg') }}" alt="{{ config('app.name', 'AsgardCRM') }}" width="36" height="36">
                        {{ config('app.name', 'Laravel') }}
                    </a>
                </div>
                <div class="card card-md">
                    <div class="card-body">
                        <h2 class="h2 text-center mb-4">{{ t('Login to your account') }}</h2>

                        <form action="{{ route('login') }}" method="POST" autocomplete="off" novalidate>
                            @csrf

                            <div class="mb-3">
                                <label for="username" class="form-label">{{ t('Username') }}</label>
                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    value="{{ old('username') }}"
                                    class="form-control @error('username') is-invalid @enderror"
                                    autofocus
                                >
                                @error('username')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-2">
                                <label for="password" class="form-label">
                                    {{ t('Password') }}
                                </label>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                >
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-check">
                                    <input type="checkbox" name="remember" class="form-check-input">
                                    <span class="form-check-label">{{ t('Remember me') }}</span>
                                </label>
                            </div>

                            <div class="form-footer">
                                <button type="submit" class="btn btn-primary w-100">
                                    {{ t('Sign in') }}
                                </button>
                            </div>
                        </form>

                        @if ($redirectProviders->isNotEmpty())
                            <div class="hr-text">{{ t('or') }}</div>

                            <div class="d-grid gap-2">
                                @foreach ($redirectProviders as $provider)
                                    <a
                                        href="{{ $provider->type === 'saml' && Route::has('login.saml.redirect') ? route('login.saml.redirect', $provider) : route('login.social.redirect', $provider) }}"
                                        class="btn btn-outline-secondary w-100"
                                        data-testid="login-provider-{{ $provider->slug }}"
                                    >
                                        {{ t('Sign in with :provider', ['provider' => $provider->name]) }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
