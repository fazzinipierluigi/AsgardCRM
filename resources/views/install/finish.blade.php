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
                        @include('crm::install._steps', ['current' => 3])

                        <h2 class="h2 text-center mb-4">Review &amp; install</h2>

                        @error('install')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        <div class="list-group list-group-flush mb-4">
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Driver</span>
                                <strong>{{ $database['driver'] }}</strong>
                            </div>
                            @if ($database['driver'] !== 'sqlite')
                                <div class="list-group-item d-flex justify-content-between">
                                    <span>Host</span>
                                    <strong>{{ $database['host'] }}:{{ $database['port'] }}</strong>
                                </div>
                            @endif
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Database</span>
                                <strong>{{ $database['database'] }}</strong>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Administrator</span>
                                <strong>{{ $admin['username'] }} ({{ $admin['email'] }})</strong>
                            </div>
                        </div>

                        <p class="text-secondary mb-4">
                            Clicking Install will write this configuration, run the database migrations, and create the administrator account above.
                        </p>

                        <form action="{{ route('install.run') }}" method="POST">
                            @csrf

                            <div class="form-footer">
                                <button type="submit" class="btn btn-primary w-100">
                                    Install
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
