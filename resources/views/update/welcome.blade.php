<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Update - {{ config('app.name', 'AsgardCRM') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('logo.svg') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
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
                        <h2 class="h2 text-center mb-4">
                            {{ $plan['direction'] === 'downgrade' ? 'Downgrade required' : 'Update required' }}
                        </h2>

                        @error('update')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        <div class="alert {{ $plan['direction'] === 'downgrade' ? 'alert-warning' : 'alert-info' }}">
                            The installed database is at version <strong>{{ $plan['from'] }}</strong>, but the
                            deployed code is version <strong>{{ $plan['to'] }}</strong>.
                            @if ($plan['direction'] === 'downgrade')
                                This will <strong>roll back</strong> the database to match.
                            @else
                                This will <strong>upgrade</strong> the database to match.
                            @endif
                        </div>

                        @if (! is_null($plan['pendingMigrations']))
                            <p class="text-secondary">
                                {{ $plan['pendingMigrations'] }} pending migration(s) detected in the app's own migrations directory.
                            </p>
                        @endif

                        @if ($plan['downgradeBlocked'])
                            <div class="alert alert-danger">
                                This database was never recorded at version {{ $plan['to'] }}, so it can't be
                                rolled back to it automatically. Restore a backup instead.
                            </div>
                        @else
                            <p class="text-secondary mb-4">
                                Clicking Update will refresh dependencies (composer/npm), run the database
                                migrations, and record the new version. This can take a few minutes — please
                                don't close this page.
                            </p>

                            <form action="{{ route('update.run') }}" method="POST">
                                @csrf

                                <div class="form-footer">
                                    <button type="submit" class="btn btn-primary w-100">
                                        Update
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
