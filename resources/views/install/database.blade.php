<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Installation - {{ config('app.name', 'AsgardCRM') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/install-wizard.js'])
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
                        @include('install._steps', ['current' => 1])

                        <h2 class="h2 text-center mb-4">Database</h2>

                        @error('database')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        <form
                            id="install-database-form"
                            action="{{ route('install.database.store') }}"
                            data-test-url="{{ route('install.database.test') }}"
                            method="POST"
                            novalidate
                        >
                            @csrf

                            <div class="mb-3">
                                <label for="driver" class="form-label">Database driver</label>
                                <select id="driver" name="driver" class="form-select @error('driver') is-invalid @enderror">
                                    @foreach (['pgsql' => 'PostgreSQL (recommended)', 'mysql' => 'MySQL (recommended)', 'mariadb' => 'MariaDB (recommended)', 'sqlite' => 'SQLite (local development only)'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('driver', $old['driver'] ?? 'pgsql') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('driver')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div id="install-db-connection-fields">
                                <div class="mb-3">
                                    <label for="host" class="form-label">Host</label>
                                    <input
                                        type="text"
                                        id="host"
                                        name="host"
                                        value="{{ old('host', $old['host'] ?? '127.0.0.1') }}"
                                        class="form-control @error('host') is-invalid @enderror"
                                    >
                                    @error('host')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="port" class="form-label">Port</label>
                                    <input
                                        type="number"
                                        id="port"
                                        name="port"
                                        value="{{ old('port', $old['port'] ?? '') }}"
                                        class="form-control @error('port') is-invalid @enderror"
                                        placeholder="5432"
                                    >
                                    @error('port')
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
                            </div>

                            <div class="mb-3">
                                <label for="database" class="form-label">Database name</label>
                                <input
                                    type="text"
                                    id="database"
                                    name="database"
                                    value="{{ old('database', $old['database'] ?? 'asgardcrm') }}"
                                    class="form-control @error('database') is-invalid @enderror"
                                >
                                @error('database')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div id="install-test-connection-result" class="alert d-none mb-3"></div>

                            <div class="form-footer d-flex gap-2">
                                <button type="button" id="install-test-connection-button" class="btn btn-outline-secondary w-100">
                                    Test connection
                                </button>
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
