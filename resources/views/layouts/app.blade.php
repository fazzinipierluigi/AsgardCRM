<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="{{ auth()->user()?->getSetting('theme', config('preferences.theme.default')) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', config('app.name', 'AsgardCRM'))</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <script>window.CSRF_TOKEN = @json(csrf_token());</script>

        <div class="page">
            <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark" data-testid="sidebar">
                <div class="container-fluid">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <a href="{{ route('dashboard') }}" class="navbar-brand navbar-brand-autodark">
                        {{ config('app.name', 'Laravel') }}
                    </a>

                    <div class="navbar-collapse collapse d-flex flex-column" id="sidebar-menu">
                        @yield('menu')
                    </div>
                </div>
            </aside>

            <header class="navbar navbar-expand-md navbar-light sticky-top d-print-none" data-testid="topnavbar">
                <div class="container-fluid">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="navbar-nav flex-row order-md-last ms-auto align-items-center">
                        <div class="nav-item dropdown me-2">
                            <a href="#" class="nav-link px-2" data-bs-toggle="dropdown" aria-label="Notifiche" data-testid="notifications-toggle">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" />
                                    <path d="M9 17v1a3 3 0 0 0 6 0v-1" />
                                </svg>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                <div class="dropdown-item text-muted">{{ __('Nessuna notifica') }}</div>
                            </div>
                        </div>

                        <div class="nav-item dropdown">
                            <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" data-testid="user-menu-toggle">
                                <span class="avatar avatar-sm">{{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                                <div class="d-none d-xl-block ps-2">
                                    <div data-testid="user-menu-name">{{ auth()->user()->name }}</div>
                                    <div class="mt-1 small text-muted" data-testid="user-menu-role">
                                        {{ auth()->user()->getRoles()->pluck('name')->join(', ') ?: __('Nessun ruolo') }}
                                    </div>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                                <a href="{{ route('settings.edit') }}" class="dropdown-item">{{ __('Impostazioni') }}</a>
                                <div class="dropdown-divider"></div>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item">{{ __('Logout') }}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="page-wrapper">
                <div class="page-header d-print-none" aria-label="Page header">
                    <div class="container-fluid">
                        <div class="row g-2 align-items-center">
                            <div class="col">
                                <h2 class="page-title">
                                    <span class="text-truncate">@yield('title', config('app.name', 'AsgardCRM'))</span>
                                </h2>
                                @hasSection('breadcrumb')
                                    <div class="mt-1">
                                        <ol class="breadcrumb breadcrumb-arrows" aria-label="breadcrumbs">
                                            <li class="breadcrumb-item">
                                                <a href="{{ url('/') }}">
                                                    <!-- Download SVG icon from http://tabler.io/icons/icon/home -->
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                         stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
                                                        <path d="M5 12l-2 0l9 -9l9 9l-2 0" />
                                                        <path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" />
                                                        <path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" />
                                                    </svg>
                                                </a>
                                            </li>
                                            @yield('breadcrumb')
                                            {{--
                                                breadcrumb section example:
                                                <li class="breadcrumb-item">
                                                    <a href="#">Library</a>
                                                </li>
                                                <li class="breadcrumb-item active" aria-current="page">
                                                    <a href="#">Articles</a>
                                                </li>
                                            --}}
                                        </ol>
                                    </div>
                                @endif
                            </div>
                            @hasSection('buttons')
                                <div class="col-auto ms-auto d-print-none">
                                    <div class="btn-list">
                                        @yield('buttons')
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="page-body">
                    <div class="container-fluid">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
