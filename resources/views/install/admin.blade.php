<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Installation - {{ config('app.name', 'AsgardCRM') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'], 'vendor/crm')
        {{ Vite::fonts() }}
    </head>
    <body class="border-top-wide border-primary d-flex flex-column">
        <div class="page page-center">
            <div class="container container-tight py-4">
                <div class="text-center mb-4">
                    <a href="{{ url('/') }}" class="navbar-brand navbar-brand-autodark wizard-brand d-flex align-items-center justify-content-center gap-2">
                        <img src="{{ asset('logo.svg') }}" alt="{{ config('app.name', 'AsgardCRM') }}" class="navbar-brand-image" width="40" height="40">
                        @include('layouts._brand')
                    </a>
                </div>
                <div class="card card-md">
                    <div class="card-body">
                        @include('install._steps', ['current' => 2])

                        <h2 class="h2 text-center mb-4">Administrator account</h2>

                        <form action="{{ route('install.admin.store') }}" method="POST" autocomplete="off" novalidate>
                            @csrf

                            <div class="mb-3">
                                <label for="name" class="form-label">Full name</label>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    value="{{ old('name', $old['name'] ?? '') }}"
                                    class="form-control @error('name') is-invalid @enderror"
                                    autofocus
                                >
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    value="{{ old('username', $old['username'] ?? '') }}"
                                    class="form-control @error('username') is-invalid @enderror"
                                >
                                @error('username')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="{{ old('email', $old['email'] ?? '') }}"
                                    class="form-control @error('email') is-invalid @enderror"
                                >
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
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
                                <label for="password_confirmation" class="form-label">Confirm password</label>
                                <input
                                    type="password"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    class="form-control"
                                >
                            </div>

                            <div class="form-footer">
                                <button type="submit" class="btn btn-primary w-100">
                                    Continue
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
