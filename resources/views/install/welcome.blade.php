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
                        @include('crm::install._steps', ['current' => 0])

                        <h2 class="h2 text-center mb-4">Welcome</h2>
                        <p class="text-secondary mb-4">
                            This wizard will help you configure the database, run migrations, and create the first administrator account.
                        </p>

                        <div class="list-group list-group-flush mb-4">
                            @foreach ($checks as $check)
                                <div class="list-group-item d-flex align-items-center justify-content-between">
                                    <span>{{ $check['label'] }}</span>
                                    @if ($check['ok'])
                                        <span class="badge bg-success-lt">OK</span>
                                    @else
                                        <span class="badge bg-danger-lt">Failed</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @php $allOk = collect($checks)->every(fn ($check) => $check['ok']); @endphp

                        @unless ($allOk)
                            <div class="alert alert-danger">
                                Fix the failed requirement(s) above before continuing.
                            </div>
                        @endunless

                        <div class="form-footer">
                            <a
                                href="{{ route('install.database') }}"
                                class="btn btn-primary w-100 {{ $allOk ? '' : 'disabled' }}"
                            >
                                Continue
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
